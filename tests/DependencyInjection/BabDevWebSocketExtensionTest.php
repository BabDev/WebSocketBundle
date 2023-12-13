<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection;

use BabDev\WebSocketBundle\Authentication\Storage\Driver\StorageDriver;
use BabDev\WebSocketBundle\DependencyInjection\BabDevWebSocketExtension;
use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\SessionAuthenticationProviderFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\ContainerHasParameterConstraint;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\DefinitionHasMethodCallConstraint;
use PHPUnit\Framework\Constraint\LogicalNot;
use React\EventLoop\LoopInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

final class BabDevWebSocketExtensionTest extends AbstractExtensionTestCase
{
    public function testContainerIsLoadedWithValidConfiguration(): void
    {
        $identity = 'BabDev-Websocket-Bundle/1.0';
        $maxRequestSize = 1024;
        $uri = 'tcp://127.0.0.1:8080';
        $context = [];
        $origins = ['example.com', 'example.net'];
        $blockedIps = ['192.168.1.1', '10.0.0.0/16'];
        $routerResource = '%kernel.project_dir%/config/websocket_router.php';

        $this->load([
            'server' => [
                'identity' => $identity,
                'max_http_request_size' => $maxRequestSize,
                'uri' => $uri,
                'context' => $context,
                'allowed_origins' => $origins,
                'blocked_ip_addresses' => $blockedIps,
                'router' => [
                    'resource' => $routerResource,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.command.run_websocket_server',
            4,
            $uri,
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.socket_server.factory.default',
            0,
            $context,
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.router',
            1,
            $routerResource,
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.request_parser',
            0,
            $maxRequestSize,
        );

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'babdev_websocket_server.server.server_middleware.parse_wamp_message',
            'setServerIdentity',
            [$identity],
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins',
            1,
            $origins,
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.server_middleware.reject_blocked_ip_address',
            1,
            $blockedIps,
        );

        self::assertThat($this->container->findDefinition('babdev_websocket_server.server.server_middleware.establish_websocket_connection'), new LogicalNot(new DefinitionHasMethodCallConstraint('enableKeepAlive')));
        $this->assertContainerBuilderHasAlias(StorageDriver::class, 'babdev_websocket_server.authentication.storage.driver.in_memory');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.initialize_session');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.factory');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.storage.factory.read_only_native');
        self::assertThat($this->container, new LogicalNot(new ContainerHasParameterConstraint('babdev_websocket_server.ping_dbal_connections', null, false)));
    }

    public function testContainerIsLoadedWithKeepaliveEnabled(): void
    {
        $this->load([
            'server' => [
                'uri' => 'tcp://127.0.0.1:8080',
                'keepalive' => [
                    'enabled' => true,
                    'interval' => 15,
                ],
                'router' => [
                    'resource' => '%kernel.project_dir%/config/websocket_router.php',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'babdev_websocket_server.server.server_middleware.establish_websocket_connection',
            'enableKeepAlive',
            [new Reference(LoopInterface::class), 15],
        );
    }

    public function testContainerIsLoadedWithDatabaseConnectionsToPing(): void
    {
        $this->load([
            'server' => [
                'uri' => 'tcp://127.0.0.1:8080',
                'periodic' => [
                    'dbal' => [
                        'connections' => [
                            'database_connection',
                        ],
                        'interval' => 15,
                    ],
                ],
                'router' => [
                    'resource' => '%kernel.project_dir%/config/websocket_router.php',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections',
            1,
            15,
        );

        $this->assertContainerBuilderHasParameter('babdev_websocket_server.ping_dbal_connections', ['database_connection']);
    }

    public function testContainerIsLoadedWithSessionAuthenticationProviderConfigured(): void
    {
        $this->load([
            'authentication' => [
                'providers' => [
                    'session' => [
                        'firewalls' => 'main',
                    ],
                ],
            ],
            'server' => [
                'uri' => 'tcp://127.0.0.1:8080',
                'router' => [
                    'resource' => '%kernel.project_dir%/config/websocket_router.php',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.authentication.authenticator',
            0,
            new IteratorArgument([new Reference('babdev_websocket_server.authentication.provider.session.default')])
        );
    }

    public function testContainerIsLoadedWithConfiguredSessionFactory(): void
    {
        $this->load([
            'server' => [
                'uri' => 'tcp://127.0.0.1:8080',
                'context' => [],
                'allowed_origins' => [],
                'blocked_ip_addresses' => [],
                'router' => [
                    'resource' => '%kernel.project_dir%/config/websocket_router.php',
                ],
                'session' => [
                    'factory_service_id' => 'session.factory.test',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.server_middleware.initialize_session',
            1,
            'session.factory.test',
        );

        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.factory');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.storage.factory.read_only_native');
    }

    public function testContainerIsLoadedWithConfiguredSessionStorageFactory(): void
    {
        $this->load([
            'server' => [
                'uri' => 'tcp://127.0.0.1:8080',
                'context' => [],
                'allowed_origins' => [],
                'blocked_ip_addresses' => [],
                'router' => [
                    'resource' => '%kernel.project_dir%/config/websocket_router.php',
                ],
                'session' => [
                    'storage_factory_service_id' => 'session.storage.factory.test',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.server_middleware.initialize_session',
            1,
            'babdev_websocket_server.server.session.factory',
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.session.factory',
            0,
            'session.storage.factory.test',
        );

        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.storage.factory.read_only_native');
    }

    public function testContainerIsLoadedWithConfiguredSessionHandler(): void
    {
        $this->load([
            'server' => [
                'uri' => 'tcp://127.0.0.1:8080',
                'context' => [],
                'allowed_origins' => [],
                'blocked_ip_addresses' => [],
                'router' => [
                    'resource' => '%kernel.project_dir%/config/websocket_router.php',
                ],
                'session' => [
                    'handler_service_id' => 'session.handler.test',
                ],
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.server_middleware.initialize_session',
            1,
            'babdev_websocket_server.server.session.factory',
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.session.factory',
            0,
            'babdev_websocket_server.server.session.storage.factory.read_only_native',
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.session.storage.factory.read_only_native',
            3,
            'session.handler.test',
        );

        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins');
    }

    /**
     * @return list<ExtensionInterface>
     */
    protected function getContainerExtensions(): array
    {
        $extension = new BabDevWebSocketExtension();
        $extension->addAuthenticationProviderFactory(new SessionAuthenticationProviderFactory());

        return [
            $extension,
        ];
    }
}
