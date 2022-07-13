<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Storage\Driver;

use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class PsrCacheStorageDriver implements StorageDriver
{
    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @throws StorageError if the token could not be deleted from storage
     */
    public function delete(string $id): bool
    {
        try {
            return $this->cache->deleteItem($id);
        } catch (CacheException $exception) {
            throw new StorageError(sprintf('Could not delete token for ID "%s" from storage.', $id), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws StorageError  if the token could not be read from storage
     * @throws TokenNotFound if a token for the given ID is not found
     */
    public function get(string $id): TokenInterface
    {
        try {
            $item = $this->cache->getItem($id);

            if (!$item->isHit()) {
                throw new TokenNotFound(sprintf('Token for ID "%s" not found.', $id));
            }

            /** @var TokenInterface */
            return $item->get();
        } catch (CacheException $exception) {
            throw new StorageError(sprintf('Could not load token for ID "%s" from storage.', $id), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws StorageError if the storage could not be checked for token presence
     */
    public function has(string $id): bool
    {
        try {
            return $this->cache->hasItem($id);
        } catch (CacheException $exception) {
            throw new StorageError(sprintf('Could not check storage for token with ID "%s".', $id), $exception->getCode(), $exception);
        }
    }

    public function store(string $id, TokenInterface $token): bool
    {
        try {
            $item = $this->cache->getItem($id);
            $item->set($token);

            return $this->cache->save($item);
        } catch (CacheException $exception) {
            throw new StorageError(sprintf('Could not store token for ID "%s" to storage.', $id), $exception->getCode(), $exception);
        }
    }
}
