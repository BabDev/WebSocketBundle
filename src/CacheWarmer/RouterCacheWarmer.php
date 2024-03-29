<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\CacheWarmer;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Generates the websocket router matcher and generator classes.
 */
final readonly class RouterCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    public function __construct(
        private ContainerInterface $container,
        private string $routerCacheDir,
    ) {}

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $router = $this->container->get('babdev_websocket_server.router');

        if ($router instanceof WarmableInterface) {
            return (array) $router->warmUp($this->routerCacheDir, $buildDir);
        }

        throw new \LogicException(sprintf('The router "%s" cannot be warmed up because it does not implement "%s".', get_debug_type($router), WarmableInterface::class));
    }

    public function isOptional(): bool
    {
        return true;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'babdev_websocket_server.router' => RouterInterface::class,
        ];
    }
}
