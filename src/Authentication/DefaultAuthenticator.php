<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\Provider\AuthenticationProvider;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

final class DefaultAuthenticator implements Authenticator, LoggerAwareInterface
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
     */
    public function authenticate(Connection $connection): void
    {
        foreach ($this->providers as $provider) {
            if (!$provider->supports($connection)) {
                $this->logger?->debug('Skipping the "{provider}" authentication provider as it did not support the connection.', ['provider' => $provider::class]);

                continue;
            }

            $token = $provider->authenticate($connection);

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

            break;
        }
    }
}
