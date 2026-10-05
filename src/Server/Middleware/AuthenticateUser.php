<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Authentication\Authenticator;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * The authenticate user server middleware authenticates the user when a connection is established.
 */
final class AuthenticateUser implements ServerMiddleware, LoggerAwareInterface
{
    use ClosesConnectionWithResponse;
    use LoggerAwareTrait;

    public function __construct(
        private readonly ServerMiddleware $middleware,
        private readonly Authenticator $authenticator,
        private readonly TokenStorage $tokenStorage,
    ) {}

    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void
    {
        try {
            $this->authenticator->authenticate($connection);
        } catch (AuthenticationException $exception) {
            $this->close($connection, 401);

            throw $exception;
        }

        $this->middleware->onOpen($connection);
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void
    {
        $this->middleware->onMessage($connection, $data);
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        // The token is removed after the decorated middleware has run so it is available while the connection closes, and even if that middleware fails
        try {
            $this->middleware->onClose($connection);
        } finally {
            $this->removeToken($connection);
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        $this->middleware->onError($connection, $throwable);
    }

    private function removeToken(Connection $connection): void
    {
        $storageId = $this->tokenStorage->generateStorageId($connection);

        $loggerContext = [
            'resource_id' => $connection->getAttributeStore()->get('resource_id'),
            'storage_id' => $storageId,
        ];

        try {
            if ($this->tokenStorage->hasToken($storageId)) {
                $token = $this->tokenStorage->getToken($storageId);

                $this->tokenStorage->removeToken($storageId);

                $this->logger?->info(
                    '{user} disconnected',
                    [...$loggerContext, 'user' => $token->getUserIdentifier() ?: 'Unknown User'],
                );
            }
        } catch (TokenNotFound $e) {
            $this->logger?->info(
                'User timed out',
                [...$loggerContext, 'exception' => $e]
            );
        } catch (StorageError $e) {
            $this->logger?->info(
                'Error processing user in storage',
                [...$loggerContext, 'exception' => $e]
            );
        }
    }
}
