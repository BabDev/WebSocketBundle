<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server;

use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocketBundle\Server\DefaultServerFactory;
use BabDev\WebSocketBundle\Server\MiddlewareStackBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;

final class DefaultServerFactoryTest extends TestCase
{
    private readonly MockObject & MiddlewareStackBuilder $middlewareStackBuilder;
    private readonly MockObject & LoopInterface $loop;

    protected function setUp(): void
    {
        $this->middlewareStackBuilder = $this->createMock(MiddlewareStackBuilder::class);
        $this->loop = $this->createMock(LoopInterface::class);
    }

    public function testCreatesAServer(): void
    {
        $factory = new DefaultServerFactory($this->middlewareStackBuilder, $this->loop, []);

        self::assertInstanceOf(ReactPhpServer::class, $factory->build('tcp://127.0.0.1:8080'));
    }
}
