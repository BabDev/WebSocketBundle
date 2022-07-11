<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocketBundle\DependencyInjection\Compiler\RoutingResolverCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RoutingResolverCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testRegistersLoadersToTheResolver(): void
    {
        $this->container->register('babdev_websocket_server.routing.resolver', LoaderResolver::class);

        $this->container->register('loader1')
            ->addTag('babdev_websocket_server.routing.loader');

        $this->container->register('loader2')
            ->addTag('babdev_websocket_server.routing.loader');

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'babdev_websocket_server.routing.resolver',
            'addLoader',
            [new Reference('loader1')]
        );

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            'babdev_websocket_server.routing.resolver',
            'addLoader',
            [new Reference('loader2')]
        );
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RoutingResolverCompilerPass());
    }
}
