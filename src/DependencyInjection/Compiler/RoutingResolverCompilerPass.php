<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds tagged "babdev_websocket_server.routing.loader" services to
 * the "babdev_websocket_server.routing.resolver" service.
 *
 * @internal
 */
final class RoutingResolverCompilerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition('babdev_websocket_server.routing.resolver')) {
            return;
        }

        $definition = $container->getDefinition('babdev_websocket_server.routing.resolver');

        foreach ($this->findAndSortTaggedServices('babdev_websocket_server.routing.loader', $container) as $id) {
            $definition->addMethodCall('addLoader', [$id]);
        }
    }
}
