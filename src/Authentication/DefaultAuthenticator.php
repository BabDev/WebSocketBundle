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
     * @param AuthenticationProvider[] $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly TokenStorage $tokenStorage
    ) {
    }

    /**
     * Attempts to authenticate the current connection.
     */
    public function authenticate(Connection $connection): void
    {
        foreach ($this->providers as $provider) {
            if (!$provider->supports($connection)) {
                $this->logger?->debug(sprintf('Skipping the "%s" authentication provider as it did not support the connection.', $provider::class));

                continue;
            }

            $token = $provider->authenticate($connection);

            $id = $this->tokenStorage->generateStorageId($connection);

            $this->tokenStorage->addToken($id, $token);

            $this->logger?->info(
                sprintf(
                    'User "%s" authenticated to websocket server',
                    method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername()
                ),
                [
                    'resource_id' => $connection->getAttributeStore()->get('resource_id'),
                    'storage_id' => $id,
                ]
            );

            break;
        }
    }
}
