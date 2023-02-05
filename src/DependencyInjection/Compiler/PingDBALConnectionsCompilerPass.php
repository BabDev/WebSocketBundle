<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Compiler;

use BabDev\WebSocketBundle\PeriodicManager\PingDoctrineDBALConnectionsPeriodicManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Tags database connection services which should be pinged by the {@see PingDoctrineDBALConnectionsPeriodicManager}.
 *
 * @internal
 */
final class PingDBALConnectionsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections') || !$container->hasParameter('babdev_websocket_server.ping_dbal_connections')) {
            $container->getParameterBag()->remove('babdev_websocket_server.ping_dbal_connections');

            return;
        }

        foreach ($container->getParameter('babdev_websocket_server.ping_dbal_connections') as $id) {
            if (!$container->has($id)) {
                throw new InvalidArgumentException(sprintf('The "%s" service does not exist in the container, please review the "server.periodic.dbal.connections" configuration for the BabDevWebSocketBundle to ensure all connections are set in your DoctrineBundle configuration.', $id));
            }

            $container->findDefinition($id)
                ->addTag('babdev_websocket_server.ping.dbal.connection');
        }

        $container->getParameterBag()->remove('babdev_websocket_server.ping_dbal_connections');
    }
}
