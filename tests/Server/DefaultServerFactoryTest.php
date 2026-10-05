<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Server\DefaultServerFactory;
use BabDev\WebSocketBundle\Server\MiddlewareStackBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
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

    public function testTheLoggerIsUsedByTheServer(): void
    {
        $logger = new TestLogger();

        $middleware = self::createStub(ServerMiddleware::class);
        $middleware->method('onError')
            ->willThrowException(new \RuntimeException('Failed to handle the error'));

        $middlewareStackBuilder = self::createStub(MiddlewareStackBuilder::class);
        $middlewareStackBuilder->method('build')
            ->willReturn($middleware);

        $server = new DefaultServerFactory($middlewareStackBuilder, self::createStub(LoopInterface::class), $logger)->build(self::createStub(ServerInterface::class));

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn(new ArrayAttributeStore());

        self::assertInstanceOf(ReactPhpServer::class, $server);

        $server->onError($connection, new \RuntimeException('Connection error'));

        self::assertTrue($logger->hasErrorThatContains('An uncaught Throwable was raised while handling an error for a connection'));
    }
}
