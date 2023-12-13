<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;
use BabDev\WebSocketBundle\Attribute\AsServerMiddleware;
use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\AuthenticationProviderFactory;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManager;
use Doctrine\DBAL\Connection;
use React\EventLoop\LoopInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class BabDevWebSocketExtension extends ConfigurableExtension
{
    /**
     * @var list<AuthenticationProviderFactory>
     */
    private array $authenticationProviderFactories = [];

    public function addAuthenticationProviderFactory(AuthenticationProviderFactory $factory): void
    {
        $this->authenticationProviderFactories[] = $factory;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->authenticationProviderFactories);
    }

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

        $container->registerAttributeForAutoconfiguration(AsServerMiddleware::class, static function (ChildDefinition $definition, AsServerMiddleware $attribute): void {
            $definition->addTag('babdev_websocket_server.server_middleware', ['priority' => $attribute->priority]);
        });

        $container->registerForAutoconfiguration(PeriodicManager::class)->addTag('babdev_websocket_server.periodic_manager');

        $this->registerAuthenticationConfiguration($mergedConfig, $container);
        $this->registerServerConfiguration($mergedConfig, $container);
    }

    private function registerAuthenticationConfiguration(array $mergedConfig, ContainerBuilder $container): void
    {
        $authenticators = [];

        if (isset($mergedConfig['authentication']['providers'])) {
            foreach ($this->authenticationProviderFactories as $factory) {
                $key = str_replace('-', '_', $factory->getKey());

                if (!isset($mergedConfig['authentication']['providers'][$key])) {
                    continue;
                }

                $authenticators[] = new Reference($factory->createAuthenticationProvider($container, $mergedConfig['authentication']['providers'][$key]));
            }
        }

        $container->getDefinition('babdev_websocket_server.authentication.authenticator')
            ->replaceArgument(0, new IteratorArgument($authenticators));
    }

    private function registerServerConfiguration(array $mergedConfig, ContainerBuilder $container): void
    {
        $container->getDefinition('babdev_websocket_server.command.run_websocket_server')
            ->replaceArgument(4, $mergedConfig['server']['uri'])
        ;

        $container->getDefinition('babdev_websocket_server.socket_server.factory.default')
            ->replaceArgument(0, $mergedConfig['server']['context'])
        ;

        $container->getDefinition('babdev_websocket_server.router')
            ->replaceArgument(1, $mergedConfig['server']['router']['resource'])
        ;

        $container->getDefinition('babdev_websocket_server.server.request_parser')
            ->replaceArgument(0, $mergedConfig['server']['max_http_request_size'])
        ;

        $container->getDefinition('babdev_websocket_server.server.server_middleware.parse_wamp_message')
            ->addMethodCall('setServerIdentity', [$mergedConfig['server']['identity']])
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

        if ($this->isConfigEnabled($container, $mergedConfig['server']['keepalive'])) {
            $container->getDefinition('babdev_websocket_server.server.server_middleware.establish_websocket_connection')
                ->addMethodCall('enableKeepAlive', [new Reference(LoopInterface::class), $mergedConfig['server']['keepalive']['interval']]);
        }

        // When we have a list of connections to ping, save it to a temporary container parameter for use in our compiler pass
        if ([] !== $mergedConfig['server']['periodic']['dbal']['connections']) {
            if (!ContainerBuilder::willBeAvailable('doctrine/dbal', Connection::class, ['doctrine/doctrine-bundle', 'babdev/money-bundle'])) {
                throw new LogicException('To configure the connections to ping, you need the Doctrine DBAL and DoctrineBundle installed. Try running "composer require doctrine/dbal doctrine/doctrine-bundle".');
            }

            $container->getDefinition('babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections')
                ->replaceArgument(1, $mergedConfig['server']['periodic']['dbal']['interval']);

            $container->setParameter('babdev_websocket_server.ping_dbal_connections', $mergedConfig['server']['periodic']['dbal']['connections']);
        } else {
            $container->removeDefinition('babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections');
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
