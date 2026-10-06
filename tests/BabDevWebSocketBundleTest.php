<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests;

use BabDev\WebSocketBundle\BabDevWebSocketBundle;
use BabDev\WebSocketBundle\DependencyInjection\BabDevWebSocketExtension;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\BuildMiddlewareStackCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\ConfigureHttpFactoriesCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\PingDBALConnectionsCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\ResolveSessionAuthenticationFirewallsCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\RoutingResolverCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

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

        foreach ([BuildMiddlewareStackCompilerPass::class, ConfigureHttpFactoriesCompilerPass::class, PingDBALConnectionsCompilerPass::class, RoutingResolverCompilerPass::class, ResolveSessionAuthenticationFirewallsCompilerPass::class] as $pass) {
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
                    'server' => ['uri' => 'tcp://127.0.0.1:8080', 'router' => ['resource' => 'websocket_router.yaml'], 'session' => ['handler_service_id' => 'session.handler.test']],
                ],
            ],
        );

        self::assertSame(['providers' => ['session' => ['firewalls' => 'main']]], $config['authentication']);
    }

    public function testSessionAuthenticationCanUseAllFirewallsWhenTheSecurityBundleIsRegisteredAfterThisBundle(): void
    {
        $container = $this->buildContainer();

        // Stands in for the SecurityBundle's extension, which sets this parameter when it is loaded
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
                $container->setParameter('security.firewalls', ['main']);
            }

            public function getAlias(): string
            {
                return 'security';
            }
        });

        $container->loadFromExtension('babdev_websocket', [
            'authentication' => ['providers' => ['session' => null]],
            'server' => ['uri' => 'tcp://127.0.0.1:8080', 'router' => ['resource' => 'websocket_router.yaml'], 'session' => ['handler_service_id' => 'session.handler.test']],
        ]);
        $container->loadFromExtension('security', []);

        new MergeExtensionConfigurationPass()->process($container);
        new ResolveSessionAuthenticationFirewallsCompilerPass()->process($container);

        self::assertSame(['main'], $container->getDefinition('babdev_websocket_server.authentication.provider.session.default')->getArgument(1));
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
