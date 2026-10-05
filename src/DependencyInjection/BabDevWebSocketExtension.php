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
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\Routing\Loader\XmlFileLoader as RoutingXmlFileLoader;

/**
 * @phpstan-type AuthenticationConfig array{providers?: array<string, array<string, mixed>>}
 * @phpstan-type SessionConfig array{factory_service_id?: string, storage_factory_service_id?: string, handler_service_id?: string}
 * @phpstan-type ServerConfig array{
 *     identity: string,
 *     max_http_request_size: int<1, max>,
 *     request_timeout: int|float|null,
 *     write_buffer_limit: int<1, max>|null,
 *     shutdown_timeout: int|float|null,
 *     max_message_payload_size: int<0, max>|null,
 *     max_frame_payload_size: int<0, max>|null,
 *     uri: string,
 *     context: mixed,
 *     allowed_origins: list<string>,
 *     blocked_ip_addresses: list<scalar>,
 *     trusted_proxies: list<string>,
 *     trusted_headers: list<string>,
 *     keepalive: array{enabled: bool, interval: int<1, max>},
 *     periodic: array{dbal: array{connections: list<scalar>, interval: int<1, max>}},
 *     router: array{resource: string},
 *     session: SessionConfig,
 * }
 * @phpstan-type BundleConfig array{authentication: AuthenticationConfig, server: ServerConfig}
 */
final class BabDevWebSocketExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    /**
     * @var list<array{int, AuthenticationProviderFactory}>
     */
    private array $authenticationProviderFactories = [];

    /**
     * @var AuthenticationProviderFactory[]
     */
    private array $sortedAuthenticationProviderFactories = [];

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        foreach ($this->getSortedAuthenticationProviderFactories() as $factory) {
            if ($factory instanceof PrependExtensionInterface) {
                $factory->prepend($container);
            }
        }
    }

    public function addAuthenticationProviderFactory(AuthenticationProviderFactory $factory): void
    {
        $this->authenticationProviderFactories[] = [$factory->getPriority(), $factory];
        $this->sortedAuthenticationProviderFactories = [];
    }

    #[\Override]
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->getSortedAuthenticationProviderFactories());
    }

    #[\Override]
    public function getAlias(): string
    {
        return 'babdev_websocket';
    }

    /**
     * @param BundleConfig $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        if (!class_exists(RoutingXmlFileLoader::class)) {
            $container->removeDefinition('babdev_websocket_server.routing.loader.xml');
        }

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

    /**
     * @param BundleConfig $mergedConfig
     */
    private function registerAuthenticationConfiguration(array $mergedConfig, ContainerBuilder $container): void
    {
        $authenticators = [];

        if (isset($mergedConfig['authentication']['providers'])) {
            foreach ($this->getSortedAuthenticationProviderFactories() as $factory) {
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

    /**
     * @param BundleConfig $mergedConfig
     */
    private function registerServerConfiguration(array $mergedConfig, ContainerBuilder $container): void
    {
        $container->getDefinition('babdev_websocket_server.command.run_websocket_server')
            ->replaceArgument(4, $mergedConfig['server']['uri'])
            ->replaceArgument(6, $mergedConfig['server']['shutdown_timeout'])
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

        if (null !== $mergedConfig['server']['request_timeout']) {
            $container->getDefinition('babdev_websocket_server.server.server_middleware.parse_http_request')
                ->addMethodCall('enableRequestTimeout', [new Reference(LoopInterface::class), $mergedConfig['server']['request_timeout']]);
        }

        $container->getDefinition('babdev_websocket_server.server.factory.default')
            ->replaceArgument(3, $mergedConfig['server']['write_buffer_limit'])
        ;

        $container->getDefinition('babdev_websocket_server.server.server_middleware.parse_wamp_message')
            ->addMethodCall('setServerIdentity', [$mergedConfig['server']['identity']])
        ;

        if ([] !== $mergedConfig['server']['allowed_origins']) {
            $container->getDefinition('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins')
                ->replaceArgument(1, $mergedConfig['server']['allowed_origins']);
        } else {
            $container->removeDefinition('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins');
        }

        if ([] !== $mergedConfig['server']['blocked_ip_addresses']) {
            $container->getDefinition('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address')
                ->replaceArgument(1, $mergedConfig['server']['blocked_ip_addresses']);
        } else {
            $container->removeDefinition('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address');
        }

        if ([] !== $mergedConfig['server']['trusted_proxies']) {
            $container->getDefinition('babdev_websocket_server.server.server_middleware.resolve_forwarded_client_address')
                ->replaceArgument(1, $mergedConfig['server']['trusted_proxies'])
                ->replaceArgument(2, $mergedConfig['server']['trusted_headers']);
        } else {
            $container->removeDefinition('babdev_websocket_server.server.server_middleware.resolve_forwarded_client_address');
        }

        $container->getDefinition('babdev_websocket_server.server.server_middleware.establish_websocket_connection')
            ->replaceArgument(2, $mergedConfig['server']['max_message_payload_size'])
            ->replaceArgument(3, $mergedConfig['server']['max_frame_payload_size'])
        ;

        if ($this->isConfigEnabled($container, $mergedConfig['server']['keepalive'])) {
            $container->getDefinition('babdev_websocket_server.server.server_middleware.establish_websocket_connection')
                ->addMethodCall('enableKeepAlive', [new Reference(LoopInterface::class), $mergedConfig['server']['keepalive']['interval']]);
        }

        // When we have a list of connections to ping, save it to a temporary container parameter for use in our compiler pass
        if ([] !== $mergedConfig['server']['periodic']['dbal']['connections']) {
            if (!ContainerBuilder::willBeAvailable('doctrine/dbal', Connection::class, ['doctrine/doctrine-bundle', 'babdev/websocket-bundle'])) {
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

    /**
     * @param SessionConfig $sessionConfig
     */
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

    /**
     * @return AuthenticationProviderFactory[]
     */
    private function getSortedAuthenticationProviderFactories(): array
    {
        if (!$this->sortedAuthenticationProviderFactories) {
            $authenticationProviderFactories = [];
            foreach ($this->authenticationProviderFactories as $i => $factory) {
                $authenticationProviderFactories[] = array_merge($factory, [$i]);
            }

            usort($authenticationProviderFactories, static fn ($a, $b) => $b[0] <=> $a[0] ?: $a[2] <=> $b[2]);

            $this->sortedAuthenticationProviderFactories = array_column($authenticationProviderFactories, 1);
        }

        return $this->sortedAuthenticationProviderFactories;
    }
}
