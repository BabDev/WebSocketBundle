<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\CacheWarmer;

use BabDev\WebSocketBundle\CacheWarmer\RouterCacheWarmer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RouterInterface;

final class RouterCacheWarmerTest extends TestCase
{
    public function testWarmUpWithWarmableInterface(): void
    {
        $cachePath = '/tmp/cache';

        /** @var MockObject&testRouterInterfaceWithWarmableInterface $router */
        $router = $this->createMock(testRouterInterfaceWithWarmableInterface::class);

        $router->expects(self::once())
            ->method('warmUp')
            ->with($cachePath)
            ->willReturn(
                [
                    UrlGenerator::class,
                    UrlMatcher::class,
                ]
            );

        /** @var MockObject&ContainerInterface $container */
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('babdev_websocket_server.router')
            ->willReturn($router);

        self::assertSame([
            UrlGenerator::class,
            UrlMatcher::class,
        ], (new RouterCacheWarmer($container, $cachePath))->warmUp('/tmp'));
    }

    public function testWarmUpWithoutWarmableInterface(): void
    {
        $this->expectException(\LogicException::class);

        /** @var MockObject&testRouterInterfaceWithoutWarmableInterface $router */
        $router = $this->createMock(testRouterInterfaceWithoutWarmableInterface::class);

        /** @var MockObject&ContainerInterface $container */
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('babdev_websocket_server.router')
            ->willReturn($router);

        (new RouterCacheWarmer($container, '/tmp/cache'))->warmUp('/tmp');
    }
}

interface testRouterInterfaceWithWarmableInterface extends RouterInterface, WarmableInterface {}

interface testRouterInterfaceWithoutWarmableInterface extends RouterInterface {}
