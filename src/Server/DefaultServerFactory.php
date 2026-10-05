<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\Server;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

final readonly class DefaultServerFactory implements ServerFactory
{
    /**
     * @param int<1, max>|null $writeBufferLimit
     */
    public function __construct(
        private MiddlewareStackBuilder $middlewareStackBuilder,
        private LoopInterface $loop,
        private ?LoggerInterface $logger = null,
        private ?int $writeBufferLimit = ReactPhpServer::DEFAULT_WRITE_BUFFER_LIMIT,
    ) {}

    public function build(ServerInterface $socket): Server
    {
        return new ReactPhpServer(
            $this->middlewareStackBuilder->build(),
            $socket,
            $this->loop,
            $this->logger,
            $this->writeBufferLimit,
        );
    }
}
