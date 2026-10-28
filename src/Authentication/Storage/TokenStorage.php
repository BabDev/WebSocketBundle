<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Storage;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Stores the security token of each connection to the websocket server under an ID generated for the connection.
 */
interface TokenStorage
{
    /**
     * Generates an identifier to be used for the token representing this connection.
     */
    public function generateStorageId(Connection $connection): string;

    /**
     * @throws StorageError if the token could not be saved to storage
     */
    public function addToken(string $id, TokenInterface $token): void;

    /**
     * @throws StorageError  if the token could not be read from storage
     * @throws TokenNotFound if a token for the specified identifier could not be found
     */
    public function getToken(string $id): TokenInterface;

    /**
     * @throws StorageError if there was an error reading from storage
     */
    public function hasToken(string $id): bool;

    /**
     * @throws StorageError if there was an error removing the token from storage
     */
    public function removeToken(string $id): bool;

    /**
     * @throws StorageError if there was an error removing any token from storage
     */
    public function removeAllTokens(): void;
}
