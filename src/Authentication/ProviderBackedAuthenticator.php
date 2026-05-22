<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Provider\AuthenticationProvider;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class ProviderBackedAuthenticator implements Authenticator, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param iterable<AuthenticationProvider> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly TokenStorage $tokenStorage
    ) {}

    /**
     * Attempts to authenticate the current connection.
     *
     * When no provider supports authenticating the connection, the authenticator will store a null token to storage.
     *
     * @throws AuthenticationException if there was an error while trying to authenticate the user
     */
    public function authenticate(Connection $connection): void
    {
        foreach ($this->providers as $provider) {
            if (!$provider->supports($connection)) {
                $this->logger?->debug('Skipping the "{provider}" authentication provider as it did not support the connection.', ['provider' => $provider::class]);

                continue;
            }

            $this->storeTokenToStorage($connection, $provider->authenticate($connection));

            return;
        }

        $this->logger?->debug('No authentication provider supported the connection, using a null token.');

        $this->storeTokenToStorage($connection, new NullToken());
    }

    private function storeTokenToStorage(Connection $connection, TokenInterface $token): void
    {
        $id = $this->tokenStorage->generateStorageId($connection);

        $this->tokenStorage->addToken($id, $token);

        $this->logger?->info(
            'User "{user}" authenticated to websocket server',
            [
                'resource_id' => $connection->getAttributeStore()->get('resource_id'),
                'storage_id' => $id,
                'user' => $token->getUserIdentifier() ?: 'Unknown User',
            ],
        );
    }
}
