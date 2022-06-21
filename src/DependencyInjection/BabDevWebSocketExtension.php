<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class BabDevWebSocketExtension extends ConfigurableExtension
{
    public function getAlias(): string
    {
        return 'babdev_websocket';
    }

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $container->getDefinition('babdev_websocket_server.command.run_websocket_server')
            ->replaceArgument(2, $mergedConfig['server']['uri'])
        ;

        $container->getDefinition('babdev_websocket_server.factory.default')
            ->replaceArgument(2, $mergedConfig['server']['context'])
        ;

        $container->getDefinition('babdev_websocket_server.server.configuration_based_middleware_stack_builder')
            ->replaceArgument(5, $mergedConfig['server']['allowed_origins'])
            ->replaceArgument(6, $mergedConfig['server']['blocked_ip_addresses'])
        ;

        $this->configureWebSocketSession($mergedConfig['server']['session'], $container);
    }

    private function configureWebSocketSession(array $sessionConfig, ContainerBuilder $container): void
    {
        if (isset($sessionConfig['factory_service_id'])) {
            $container->removeDefinition('babdev_websocket_server.server.session.factory');
            $container->removeDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native');

            $container->getDefinition('babdev_websocket_server.server.configuration_based_middleware_stack_builder')
                ->replaceArgument(4, new Reference($sessionConfig['factory_service_id']))
            ;

            return;
        }

        if (isset($sessionConfig['storage_factory_service_id'])) {
            $container->removeDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native');

            $container->getDefinition('babdev_websocket_server.server.session.factory')
                ->replaceArgument(0, new Reference($sessionConfig['storage_factory_service_id']))
            ;

            $container->getDefinition('babdev_websocket_server.server.configuration_based_middleware_stack_builder')
                ->replaceArgument(4, new Reference('babdev_websocket_server.server.session.factory'))
            ;

            return;
        }

        if (isset($sessionConfig['handler_service_id'])) {
            $container->getDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native')
                ->replaceArgument(3, new Reference($sessionConfig['handler_service_id']))
            ;

            $container->getDefinition('babdev_websocket_server.server.session.factory')
                ->replaceArgument(0, new Reference('babdev_websocket_server.server.session.storage.factory.read_only_native'))
            ;

            $container->getDefinition('babdev_websocket_server.server.configuration_based_middleware_stack_builder')
                ->replaceArgument(4, new Reference('babdev_websocket_server.server.session.factory'))
            ;

            return;
        }

        // The session is not available through the bundle configuration, remove the session factories
        $container->removeDefinition('babdev_websocket_server.server.session.factory');
        $container->removeDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native');
    }
}
