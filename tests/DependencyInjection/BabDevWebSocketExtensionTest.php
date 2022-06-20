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

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.configuration_based_middleware_stack_builder',
            4,
            $origins,
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'babdev_websocket_server.server.configuration_based_middleware_stack_builder',
            5,
            $blockedIps,
        );
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
