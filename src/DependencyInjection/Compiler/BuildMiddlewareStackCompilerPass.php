<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Compiler;

use BabDev\WebSocket\Server\ServerMiddleware;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The build middleware stack compiler pass creates a {@see ServerMiddleware} service by processing services with the
 * "babdev.websocket_server.server_middleware" tag, sorted by priority.
 *
 * Services using this tag must receive the decorated middleware as the first argument to its class constructor.
 *
 * @internal
 */
final class BuildMiddlewareStackCompilerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        /** @var Reference|null $previousMiddleware */
        $previousMiddleware = null;

        /** @var Reference|null $outerMiddleware */
        $outerMiddleware = null;

        foreach ($this->findAndSortTaggedServices('babdev.websocket_server.server_middleware', $container) as $middleware) {
            if (null === $previousMiddleware) {
                $previousMiddleware = $middleware;

                continue;
            }

            $container->getDefinition((string) $middleware)
                ->replaceArgument(0, $previousMiddleware);

            $previousMiddleware = $middleware;
            $outerMiddleware = $middleware;
        }

        $container->setAlias(ServerMiddleware::class, new Alias((string) $outerMiddleware));
    }
}
