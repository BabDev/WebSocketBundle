<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Authentication\Authenticator;
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
    use LoggerAwareTrait;

    public function __construct(
        private readonly ServerMiddleware $middleware,
        private readonly Authenticator $authenticator,
        private readonly TokenStorage $tokenStorage,
    ) {
    }

    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void
    {
        $this->authenticator->authenticate($connection);

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
        $this->middleware->onClose($connection);

        $storageId = $this->tokenStorage->generateStorageId($connection);

        $loggerContext = [
            'connection_id' => $connection->getAttributeStore()->get('resource_id'),
            'session_id' => $connection->getAttributeStore()->get('wamp.session_id'),
            'storage_id' => $storageId,
        ];

        try {
            if ($this->tokenStorage->hasToken($storageId)) {
                $token = $this->tokenStorage->getToken($storageId);

                $this->tokenStorage->removeToken($storageId);

                $userIdentifier = method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername();

                $this->logger?->info(
                    sprintf('%s disconnected', $userIdentifier),
                    array_merge(
                        $loggerContext,
                        ['user_identifier' => $userIdentifier]
                    )
                );
            }
        } catch (TokenNotFound $e) {
            $this->logger?->info(
                'User timed out',
                array_merge(
                    $loggerContext,
                    ['exception' => $e]
                )
            );
        } catch (StorageError $e) {
            $this->logger?->info(
                'Error processing user in storage',
                array_merge(
                    $loggerContext,
                    ['exception' => $e]
                )
            );
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        $this->middleware->onError($connection, $throwable);
    }
}
