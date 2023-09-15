<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use React\Socket\SocketServer;

final readonly class DefaultSocketServerFactory implements SocketServerFactory
{
    public function __construct(
        private array $context,
        private LoopInterface $loop,
    ) {}

    public function build(string $uri): ServerInterface
    {
        return new SocketServer($uri, $this->context, $this->loop);
    }
}
