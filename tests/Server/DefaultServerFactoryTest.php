<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server;

use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocketBundle\Server\DefaultServerFactory;
use BabDev\WebSocketBundle\Server\MiddlewareStackBuilder;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

final class DefaultServerFactoryTest extends TestCase
{
    public function testCreatesAServer(): void
    {
        self::assertInstanceOf(
            ReactPhpServer::class,
            new DefaultServerFactory(self::createStub(MiddlewareStackBuilder::class), self::createStub(LoopInterface::class))->build(self::createStub(ServerInterface::class)),
        );
    }
}
