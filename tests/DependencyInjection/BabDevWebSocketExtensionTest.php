<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection;

use BabDev\WebSocketBundle\DependencyInjection\BabDevWebSocketExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class BabDevWebSocketExtensionTest extends AbstractExtensionTestCase
{
    public function testContainerIsLoadedWithValidConfiguration(): void
    {
        $uri = 'tcp://127.0.0.1:8080';
        $origins = ['example.com', 'example.net'];
        $blockedIps = ['192.168.1.1', '10.0.0.0/16'];

        $this->load([
            'server' => [
                'uri' => $uri,
                'context' => [],
                'allowed_origins' => $origins,
                'blocked_ip_addresses' => $blockedIps,
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.command.run_websocket_server',
            2,
            $uri,
        );

        foreach ($origins as $origin) {
            $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
                'babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins',
                'allowOrigin',
                [$origin],
            );
        }

        foreach ($blockedIps as $blockedIp) {
            $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
                'babdev_websocket_server.server.server_middleware.reject_blocked_ip_address',
                'blockAddress',
                [$blockedIp],
            );
        }

        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.server_middleware.initialize_session');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.factory');
        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.storage.factory.read_only_native');
    }

    public function testContainerIsLoadedWithConfiguredSessionFactory(): void
    {
        $this->load([
            'server' => [
                'uri' => 'tcp://127.0.0.1:8080',
                'context' => [],
                'allowed_origins' => [],
                'blocked_ip_addresses' => [],
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
     * @return ExtensionInterface[]
     */
    protected function getContainerExtensions(): array
    {
        return [
            new BabDevWebSocketExtension(),
        ];
    }
}
