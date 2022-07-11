<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
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

        $container->registerAttributeForAutoconfiguration(AsMessageHandler::class, static function (ChildDefinition $definition, AsMessageHandler $attribute): void {
            $definition->addTag('babdev_websocket_server.message_handler');
        });

        $container->getDefinition('babdev_websocket_server.command.run_websocket_server')
            ->replaceArgument(3, $mergedConfig['server']['uri'])
        ;

        $container->getDefinition('babdev_websocket_server.socket_server.factory.default')
            ->replaceArgument(0, $mergedConfig['server']['context'])
        ;

        $container->getDefinition('babdev_websocket_server.router')
            ->replaceArgument(1, $mergedConfig['server']['router']['resource'])
        ;

        if ([] !== $mergedConfig['server']['allowed_origins']) {
            $definition = $container->getDefinition('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins');

            foreach ($mergedConfig['server']['allowed_origins'] as $origin) {
                $definition->addMethodCall('allowOrigin', [$origin]);
            }
        } else {
            $container->removeDefinition('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins');
        }

        if ([] !== $mergedConfig['server']['blocked_ip_addresses']) {
            $definition = $container->getDefinition('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address');

            foreach ($mergedConfig['server']['blocked_ip_addresses'] as $address) {
                $definition->addMethodCall('blockAddress', [$address]);
            }
        } else {
            $container->removeDefinition('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address');
        }

        $this->configureWebSocketSession($mergedConfig['server']['session'], $container);
    }

    private function configureWebSocketSession(array $sessionConfig, ContainerBuilder $container): void
    {
        if (isset($sessionConfig['factory_service_id'])) {
            $container->removeDefinition('babdev_websocket_server.server.session.factory');
            $container->removeDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native');

            $container->getDefinition('babdev_websocket_server.server.server_middleware.initialize_session')
                ->replaceArgument(1, new Reference($sessionConfig['factory_service_id']))
            ;

            return;
        }

        if (isset($sessionConfig['storage_factory_service_id'])) {
            $container->removeDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native');

            $container->getDefinition('babdev_websocket_server.server.session.factory')
                ->replaceArgument(0, new Reference($sessionConfig['storage_factory_service_id']))
            ;

            $container->getDefinition('babdev_websocket_server.server.server_middleware.initialize_session')
                ->replaceArgument(1, new Reference('babdev_websocket_server.server.session.factory'))
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

            $container->getDefinition('babdev_websocket_server.server.server_middleware.initialize_session')
                ->replaceArgument(1, new Reference('babdev_websocket_server.server.session.factory'))
            ;

            return;
        }

        // The session is not available through the bundle configuration, remove the session factories and middleware
        $container->removeDefinition('babdev_websocket_server.server.server_middleware.initialize_session');
        $container->removeDefinition('babdev_websocket_server.server.session.factory');
        $container->removeDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native');
    }
}
