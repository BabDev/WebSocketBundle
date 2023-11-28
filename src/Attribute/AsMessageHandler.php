<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Attribute;

use Symfony\Component\Routing\Annotation\Route;

/**
 * Attribute used to configure message handlers.
 *
 * This attribute serves two purposes:
 *
 * 1) Register the message handler as a service within the container
 * 2) Configure the route definition for the message handler to be used with the websocket server's router
 *
 * Because of the second purpose, this attribute purposefully decorates the {@see Route} annotation/attribute class
 * from Symfony's Routing component to allow using its annotation/attribute loaders.
 *
 * @mixin Route
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsMessageHandler
{
    private readonly Route $route;

    public function __construct(mixed ...$args)
    {
        $this->route = new Route(...$args);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (!method_exists($this->route, $name)) {
            throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s().', self::class, $name));
        }

        if (str_starts_with($name, 'get')) {
            return $this->route->$name(...$arguments);
        }

        $this->route->$name(...$arguments);

        return null;
    }
}
