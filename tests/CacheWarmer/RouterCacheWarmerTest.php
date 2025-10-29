<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\CacheWarmer;

use BabDev\WebSocketBundle\CacheWarmer\RouterCacheWarmer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;

final class RouterCacheWarmerTest extends TestCase
{
    public function testWarmUpWithNoBuildDir(): void
    {
        $cacheDir = '/tmp/cache';
        $cacheFolder = 'websocket-router';

        /** @var MockObject&CacheWarmerInterface $innerCacheWarmer */
        $innerCacheWarmer = $this->createMock(CacheWarmerInterface::class);

        $innerCacheWarmer->expects(self::once())
            ->method('warmUp')
            ->with(\sprintf('%s/%s', $cacheDir, $cacheFolder), null)
            ->willReturn([
                UrlGenerator::class,
                UrlMatcher::class,
            ]);

        self::assertSame([
            UrlGenerator::class,
            UrlMatcher::class,
        ], new RouterCacheWarmer($innerCacheWarmer, $cacheFolder)->warmUp($cacheDir, null));
    }

    public function testWarmUpWithBuildDir(): void
    {
        $buildDir = '/tmp/build';
        $cacheDir = '/tmp/cache';
        $cacheFolder = 'websocket-router';

        /** @var MockObject&CacheWarmerInterface $innerCacheWarmer */
        $innerCacheWarmer = $this->createMock(CacheWarmerInterface::class);

        $innerCacheWarmer->expects(self::once())
            ->method('warmUp')
            ->with(\sprintf('%s/%s', $cacheDir, $cacheFolder), \sprintf('%s/%s', $buildDir, $cacheFolder))
            ->willReturn([
                UrlGenerator::class,
                UrlMatcher::class,
            ]);

        self::assertSame([
            UrlGenerator::class,
            UrlMatcher::class,
        ], new RouterCacheWarmer($innerCacheWarmer, $cacheFolder)->warmUp($cacheDir, $buildDir));
    }
}
