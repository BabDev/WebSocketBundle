<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
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
            ->replaceArgument(4, $mergedConfig['server']['allowed_origins'])
            ->replaceArgument(5, $mergedConfig['server']['blocked_ip_addresses'])
        ;
    }
}
