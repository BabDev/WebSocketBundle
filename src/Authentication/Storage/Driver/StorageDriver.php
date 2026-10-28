<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Storage\Driver;

use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Saves, reads and deletes security tokens by ID, as the backend of the {@see TokenStorage}.
 */
interface StorageDriver
{
    public function clear(): void;

    /**
     * @throws StorageError if the token could not be deleted from storage
     */
    public function delete(string $id): bool;

    /**
     * @throws StorageError  if the token could not be read from storage
     * @throws TokenNotFound if a token for the given ID is not found
     */
    public function get(string $id): TokenInterface;

    /**
     * @throws StorageError if the storage could not be checked for token presence
     */
    public function has(string $id): bool;

    /**
     * @throws StorageError if the token could not be saved to storage
     */
    public function store(string $id, TokenInterface $token): bool;
}
