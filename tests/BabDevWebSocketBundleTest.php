<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests;

use BabDev\WebSocketBundle\BabDevWebSocketBundle;
use BabDev\WebSocketBundle\DependencyInjection\BabDevWebSocketExtension;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\BuildMiddlewareStackCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\ConfigureHttpFactoriesCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\PingDBALConnectionsCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\RoutingResolverCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BabDevWebSocketBundleTest extends TestCase
{
    public function testTheContainerExtensionIsProvided(): void
    {
        $bundle = new BabDevWebSocketBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(BabDevWebSocketExtension::class, $extension);
        self::assertSame('babdev_websocket', $extension->getAlias());
        self::assertSame($extension, $bundle->getContainerExtension(), 'The same extension instance should be returned.');
    }

    public function testTheCompilerPassesAreRegistered(): void
    {
        $container = $this->buildContainer();

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        foreach ([BuildMiddlewareStackCompilerPass::class, ConfigureHttpFactoriesCompilerPass::class, PingDBALConnectionsCompilerPass::class, RoutingResolverCompilerPass::class] as $pass) {
            self::assertContains($pass, $passes);
        }
    }

    public function testTheSessionAuthenticationProviderCanBeConfigured(): void
    {
        $container = $this->buildContainer();

        /** @var BabDevWebSocketExtension $extension */
        $extension = $container->getExtension('babdev_websocket');

        $config = new Processor()->processConfiguration(
            $extension->getConfiguration([], $container),
            [
                [
                    'authentication' => ['providers' => ['session' => ['firewalls' => 'main']]],
                    'server' => ['uri' => 'tcp://127.0.0.1:8080', 'router' => ['resource' => 'websocket_router.yaml']],
                ],
            ],
        );

        self::assertSame(['providers' => ['session' => ['firewalls' => 'main']]], $config['authentication']);
    }

    public function testTheBundlePathIsThePackageRoot(): void
    {
        self::assertSame(\dirname(__DIR__), new BabDevWebSocketBundle()->getPath());
    }

    private function buildContainer(): ContainerBuilder
    {
        $bundle = new BabDevWebSocketBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(BabDevWebSocketExtension::class, $extension);

        $container = new ContainerBuilder();
        $container->registerExtension($extension);

        $bundle->build($container);

        return $container;
    }
}
