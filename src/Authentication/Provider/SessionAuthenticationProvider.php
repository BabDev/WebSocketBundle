<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

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
    ) {}

    public function supports(Connection $connection): bool
    {
        $attributeStore = $connection->getAttributeStore();

        return $attributeStore->has('session') && $attributeStore->get('session') instanceof SessionInterface;
    }

    public function authenticate(Connection $connection): TokenInterface
    {
        $token = $this->getToken($connection);

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

        foreach ($this->firewalls as $firewall) {
            if (false !== $serializedToken = $session->get('_security_'.$firewall, false)) {
                $token = unserialize($serializedToken);

                break;
            }
        }

        if (!$token instanceof TokenInterface) {
            $token = new NullToken();
        }

        return $token;
    }
}
