<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\Server;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

final class DefaultServerFactory implements ServerFactory
{
    public function __construct(
        private readonly MiddlewareStackBuilder $middlewareStackBuilder,
        private readonly LoopInterface $loop,
    ) {
    }

    public function build(ServerInterface $socket): Server
    {
        return new ReactPhpServer(
            $this->middlewareStackBuilder->build(),
            $socket,
            $this->loop,
        );
    }
}
