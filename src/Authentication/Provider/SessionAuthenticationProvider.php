<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\WebSocketException;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Exception\InvalidTokenException;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @param list<string> $firewalls
     */
    public function __construct(
        private readonly TokenStorage $tokenStorage,
        private readonly array $firewalls,
        private readonly OptionsHandler $optionsHandler = new IniOptionsHandler(),
    ) {}

    public function supports(Connection $connection): bool
    {
        $attributeStore = $connection->getAttributeStore();

        return $attributeStore->has('session') && $attributeStore->get('session') instanceof SessionInterface;
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

        $storageId = $this->tokenStorage->generateStorageId($connection);

        $this->tokenStorage->addToken($storageId, $token);

        $this->logger?->info(
            '{user} connected',
            [
                'resource_id' => $connection->getAttributeStore()->get('resource_id'),
                'storage_id' => $storageId,
                'user' => $token->getUserIdentifier() ?: 'Unknown User',
            ],
        );

        return $token;
    }

    private function getToken(Connection $connection): TokenInterface
    {
        $token = null;

        /** @var SessionInterface $session */
        $session = $connection->getAttributeStore()->get('session');

        $sessionKey = null;

        foreach ($this->firewalls as $firewall) {
            if (false !== $serializedToken = $session->get($sessionKey = '_security_'.$firewall, false)) {
                if (!is_string($serializedToken)) {
                    $this->logger?->debug('Session has a non-string serialized token.', [
                        'key' => $sessionKey,
                        'type' => get_debug_type($serializedToken),
                    ]);

                    break;
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
        } elseif (null !== $token) {
            $this->logger?->warning('Expected a security token from the session, got something else.', ['key' => $sessionKey, 'received' => $token]);

            $token = null;
        }

        return $token ?? new NullToken();
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
