<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use React\Socket\SocketServer;

final class DefaultSocketServerFactory implements SocketServerFactory
{
    public function __construct(
        private readonly array $context,
        private readonly LoopInterface $loop,
    ) {}

    public function build(string $uri): ServerInterface
    {
        return new SocketServer($uri, $this->context, $this->loop);
    }
}
