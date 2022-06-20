<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\Server;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;

final class DefaultServerFactory implements ServerFactory
{
    public function __construct(
        private readonly MiddlewareStackBuilder $middlewareStackBuilder,
        private readonly LoopInterface $loop,
        private readonly array $context,
    ) {
    }

    public function build(string $uri): Server
    {
        return new ReactPhpServer(
            $this->middlewareStackBuilder->build(),
            new SocketServer($uri, $this->context, $this->loop),
            $this->loop,
        );
    }
}
