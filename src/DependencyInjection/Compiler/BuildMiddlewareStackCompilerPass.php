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
 * "babdev_websocket_server.server_middleware" tag, sorted by priority.
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

        foreach ($this->findAndSortTaggedServices('babdev_websocket_server.server_middleware', $container) as $middleware) {
            if ($previousMiddleware instanceof Reference) {
                // Autowired services may not have any arguments configured yet, so the argument is set instead of replaced
                $container->getDefinition((string) $middleware)
                    ->setArgument(0, $previousMiddleware);
            }

            $previousMiddleware = $middleware;
        }

        // The last middleware in the stack is the outermost one, and is left unaliased when there is no middleware so the stack builder can report it
        if ($previousMiddleware instanceof Reference) {
            $container->setAlias(ServerMiddleware::class, new Alias((string) $previousMiddleware));
        }
    }
}
