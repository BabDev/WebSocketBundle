<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Fixtures;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Attribute\AsServerMiddleware;

#[AsServerMiddleware(priority: -100)]
final readonly class AttributedServerMiddleware implements ServerMiddleware
{
    public function __construct(
        private ServerMiddleware $middleware,
    ) {}

    public function onOpen(Connection $connection): void
    {
        $this->middleware->onOpen($connection);
    }

    public function onMessage(Connection $connection, string $data): void
    {
        $this->middleware->onMessage($connection, $data);
    }

    public function onClose(Connection $connection): void
    {
        $this->middleware->onClose($connection);
    }

    public function onError(Connection $connection, \Throwable $throwable): void
    {
        $this->middleware->onError($connection, $throwable);
    }
}
