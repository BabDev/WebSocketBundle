<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\WebSocketException;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Exception\InvalidTokenException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * The session authentication provider uses the HTTP session for your website's frontend for authenticating to the websocket server.
 *
 * The provider will by default attempt to authenticate with any of your site's configured firewalls, using the token
 * from the first matched firewall in your configuration. You may optionally configure the provider to use only selected
 * firewalls for authenticated.
 */
final class SessionAuthenticationProvider implements AuthenticationProvider, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param list<string>    $firewalls     The security contexts of the firewalls whose token can be read from the session
     * @param iterable<mixed> $userProviders The user providers used to refresh the user from the token, the same as the SecurityBundle uses when reading the token for an HTTP request
     */
    public function __construct(
        private readonly array $firewalls,
        private readonly OptionsHandler $optionsHandler = new IniOptionsHandler(),
        private readonly iterable $userProviders = [],
    ) {}

    public function supports(Connection $connection): bool
    {
        $attributeStore = $connection->getAttributeStore();

        return $attributeStore->has(AttributeKey::SESSION) && $attributeStore->get(AttributeKey::SESSION) instanceof SessionInterface;
    }

    /**
     * @throws AuthenticationException if there was an error while trying to authenticate the user
     */
    public function authenticate(Connection $connection): TokenInterface
    {
        try {
            $token = $this->getToken($connection);
        } catch (WebSocketException $exception) {
            $this->logger?->error('Could not authenticate user.', ['exception' => $exception]);

            throw new AuthenticationException('Could not authenticate user.', previous: $exception);
        }

        return $token;
    }

    private function getToken(Connection $connection): TokenInterface
    {
        $token = null;

        /** @var SessionInterface $session */
        $session = $connection->getAttributeStore()->get(AttributeKey::SESSION);

        $sessionKey = null;

        foreach ($this->firewalls as $firewall) {
            if (false !== $serializedToken = $session->get($sessionKey = '_security_'.$firewall, false)) {
                if (!\is_string($serializedToken)) {
                    $this->logger?->debug('Session has a non-string serialized token.', [
                        'key' => $sessionKey,
                        'type' => get_debug_type($serializedToken),
                    ]);

                    continue;
                }

                $token = $this->safelyUnserialize($serializedToken, $sessionKey);

                $this->logger?->debug('Read existing security token from the session.', [
                    'key' => $sessionKey,
                    'token_class' => \is_object($token) ? $token::class : null,
                ]);

                break;
            }
        }

        if ($token instanceof TokenInterface) {
            if (!$token->getUser() instanceof UserInterface) {
                throw new InvalidTokenException(\sprintf('Cannot authenticate a "%s" token because it doesn\'t store a user.', $token::class));
            }

            $token = $this->refreshUserFromToken($token);
        } elseif (null !== $token) {
            $this->logger?->warning('Expected a security token from the session, got something else.', ['key' => $sessionKey, 'received' => $token]);

            $token = null;
        }

        return $token ?? new NullToken();
    }

    /**
     * Refreshes the user from the token in the same way as the SecurityBundle does when reading the token for an HTTP request.
     *
     * Without any user providers, such as when the SecurityBundle is not installed, the token is used as is.
     *
     * @throws \RuntimeException if no user provider supports the user from the token
     */
    private function refreshUserFromToken(TokenInterface $token): TokenInterface
    {
        if (!$this->hasUserProviders()) {
            return $token;
        }

        $refreshedToken = $this->refreshUser($token);

        if (!$refreshedToken instanceof TokenInterface) {
            $this->logger?->debug('Token was deauthenticated after trying to refresh it.');

            return new NullToken();
        }

        return $refreshedToken;
    }

    private function hasUserProviders(): bool
    {
        // The providers may be a lazy iterable from the container, which can only be checked by iterating it
        foreach ($this->userProviders as $provider) {
            return true;
        }

        return false;
    }

    /**
     * Forked from {@see \Symfony\Component\Security\Http\Firewall\ContextListener::refreshUser}.
     *
     * @throws \RuntimeException if no user provider supports the user from the token
     */
    private function refreshUser(TokenInterface $token): ?TokenInterface
    {
        if ($token instanceof SwitchUserToken && $token->getOriginalToken()->getUser() && !$this->refreshUser($token->getOriginalToken())) {
            return null;
        }

        /** @var UserInterface $user */
        $user = $token->getUser();

        $userNotFoundByProvider = false;
        $userDeauthenticated = false;
        $userClass = $user::class;

        foreach ($this->userProviders as $provider) {
            if (!$provider instanceof UserProviderInterface) {
                throw new \InvalidArgumentException(\sprintf('User provider "%s" must implement "%s".', get_debug_type($provider), UserProviderInterface::class));
            }

            if (!$provider->supportsClass($userClass)) {
                continue;
            }

            try {
                $refreshedUser = $provider->refreshUser($user);

                // tokens can be deauthenticated if the user has been changed.
                if ($token instanceof AbstractToken && self::hasUserChanged($token, $user, $refreshedUser)) {
                    $userDeauthenticated = true;

                    $this->logger?->debug('Cannot refresh token because user has changed.', ['username' => $refreshedUser->getUserIdentifier(), 'provider' => $provider::class]);

                    continue;
                }

                $token->setUser($refreshedUser);

                if (null !== $this->logger) {
                    $context = ['provider' => $provider::class, 'username' => $refreshedUser->getUserIdentifier()];

                    if ($token instanceof SwitchUserToken) {
                        $originalToken = $token->getOriginalToken();
                        $context['impersonator_username'] = $originalToken->getUserIdentifier();
                    }

                    $this->logger->debug('User was reloaded from a user provider.', $context);
                }

                return $token;
            } catch (UnsupportedUserException) {
                // let's try the next user provider
            } catch (UserNotFoundException $e) {
                $this->logger?->info('Username could not be found in the selected user provider.', ['username' => $e->getUserIdentifier(), 'provider' => $provider::class]);

                $userNotFoundByProvider = true;
            }
        }

        if ($userDeauthenticated) {
            return null;
        }

        if ($userNotFoundByProvider) {
            return null;
        }

        throw new \RuntimeException(\sprintf('There is no user provider for user "%s". Shouldn\'t the "supportsClass()" method of your user provider return true for this classname?', $userClass));
    }

    /**
     * Forked from {@see \Symfony\Component\Security\Http\Firewall\ContextListener::hasUserChanged}.
     */
    private static function hasUserChanged(AbstractToken $token, UserInterface $originalUser, UserInterface $refreshedUser): bool
    {
        if ($originalUser instanceof EquatableInterface) {
            return !$originalUser->isEqualTo($refreshedUser);
        }

        if ($originalUser instanceof PasswordAuthenticatedUserInterface || $refreshedUser instanceof PasswordAuthenticatedUserInterface) {
            if (!$originalUser instanceof PasswordAuthenticatedUserInterface || !$refreshedUser instanceof PasswordAuthenticatedUserInterface) {
                return true;
            }

            $originalPassword = $originalUser->getPassword();
            $refreshedPassword = $refreshedUser->getPassword();

            if (null !== $originalPassword
                && $refreshedPassword !== $originalPassword
                && (8 !== \strlen($originalPassword) || hash('crc32c', $refreshedPassword ?? $originalPassword) !== $originalPassword)
            ) {
                return true;
            }

            if ($originalUser instanceof LegacyPasswordAuthenticatedUserInterface xor $refreshedUser instanceof LegacyPasswordAuthenticatedUserInterface) {
                return true;
            }

            // Unlike the original, the refreshed user is also checked so the salt can be read, which is equivalent after the check above
            if ($originalUser instanceof LegacyPasswordAuthenticatedUserInterface && $refreshedUser instanceof LegacyPasswordAuthenticatedUserInterface && $originalUser->getSalt() !== $refreshedUser->getSalt()) {
                return true;
            }
        }

        $userRoles = array_map('strval', (array) $refreshedUser->getRoles());
        $tokenRoleNames = $token->getRoleNames();

        if (
            \count($userRoles) !== \count($tokenRoleNames)
            || \count($userRoles) !== \count(array_intersect($userRoles, $tokenRoleNames))
        ) {
            return true;
        }

        if ($originalUser->getUserIdentifier() !== $refreshedUser->getUserIdentifier()) {
            return true;
        }

        return false;
    }

    /**
     * Safely unserialize a token from the session store.
     *
     * Forked from {@see \Symfony\Component\Security\Http\Firewall\ContextListener::safelyUnserialize}
     */
    private function safelyUnserialize(string $serializedToken, string $sessionKey): mixed
    {
        $token = null;

        $prevUnserializeHandler = $this->optionsHandler->set('unserialize_callback_func', self::class.'::handleUnserializeCallback');

        $prevErrorHandler = set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$prevErrorHandler): bool {
            if (__FILE__ === $errfile && !\in_array($errno, [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
                throw new \ErrorException($errstr, 0x37313BC, $errno, $errfile, $errline);
            }

            /** @phpstan-ignore return.type */
            return $prevErrorHandler ? $prevErrorHandler($errno, $errstr, $errfile, $errline) : false;
        });

        try {
            $token = unserialize($serializedToken);
            // @phpstan-ignore catch.neverThrown (The error handler and unserialize callback above throw this exception from within unserialize())
        } catch (\ErrorException $e) {
            if (0x37313BC !== $e->getCode()) {
                throw $e;
            }

            $this->logger?->warning('Failed to unserialize the security token from the session.', ['key' => $sessionKey, 'received' => $serializedToken, 'exception' => $e]);
        } finally {
            restore_error_handler();

            $this->optionsHandler->set('unserialize_callback_func', $prevUnserializeHandler);
        }

        return $token;
    }

    /**
     * @param class-string $class
     *
     * @internal
     */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new \ErrorException('Class not found: '.$class, 0x37313BC);
    }
}
