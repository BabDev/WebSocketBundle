<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Storage;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\Storage\Driver\StorageDriver;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class DriverBackedTokenStorage implements TokenStorage, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private readonly StorageDriver $driver) {}

    public function generateStorageId(Connection $connection): string
    {
        return (string) $connection->getAttributeStore()->get('resource_id', '');
    }

    /**
     * @throws StorageError if the token could not be saved to storage
     */
    public function addToken(string $id, TokenInterface $token): void
    {
        $this->logger?->debug(
            'Adding token for connection ID {connection} to storage.',
            [
                'connection' => $id,
                'token' => $token,
                'username' => $token->getUserIdentifier() ?: 'Unknown User',
            ],
        );

        $result = $this->driver->store($id, $token);

        if (false === $result) {
            throw new StorageError(sprintf('Unable to add client "%s" to storage', $token->getUserIdentifier()));
        }
    }

    /**
     * @throws StorageError  if the token could not be read from storage
     * @throws TokenNotFound if a token for the specified identifier could not be found
     */
    public function getToken(string $id): TokenInterface
    {
        $this->logger?->debug('Retrieving token for connection ID {connection} from storage.', ['connection' => $id]);

        return $this->driver->get($id);
    }

    /**
     * @throws StorageError if there was an error reading from storage
     */
    public function hasToken(string $id): bool
    {
        return $this->driver->has($id);
    }

    /**
     * @throws StorageError if there was an error removing the token from storage
     */
    public function removeToken(string $id): bool
    {
        $this->logger?->debug('Removing token for connection ID {connection} from storage.', ['connection' => $id]);

        return $this->driver->delete($id);
    }

    /**
     * @throws StorageError if there was an error removing any token from storage
     */
    public function removeAllTokens(): void
    {
        $this->logger?->debug('Removing all tokens from storage');

        $this->driver->clear();
    }
}
