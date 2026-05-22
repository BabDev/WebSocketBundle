<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server;

use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Exception\MiddlewareNotConfigured;
use BabDev\WebSocketBundle\Server\ServiceBasedMiddlewareStackBuilder;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ServiceBasedMiddlewareStackBuilderTest extends TestCase
{
    public function testReturnsInjectedMiddlewareStack(): void
    {
        /** @var Stub&ServerMiddleware $middleware */
        $middleware = self::createStub(ServerMiddleware::class);

        self::assertSame($middleware, new ServiceBasedMiddlewareStackBuilder($middleware)->build());
    }

    public function testRaisesAnErrorWhenTheMiddlewareIsNotInjected(): void
    {
        $this->expectException(MiddlewareNotConfigured::class);

        new ServiceBasedMiddlewareStackBuilder()->build();
    }
}
