<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Attribute\AsServerMiddleware;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\BuildMiddlewareStackCompilerPass;
use BabDev\WebSocketBundle\Tests\Fixtures\AttributedServerMiddleware;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\DefinitionHasArgumentConstraint;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class BuildMiddlewareStackCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testMiddlewareStackIsProcessed(): void
    {
        $this->container->register('middleware.outer', ServerMiddleware::class)
            ->addArgument(new AbstractArgument('decorated middleware'))
            ->addTag('babdev_websocket_server.server_middleware', ['priority' => -10]);

        $this->container->register('middleware.inner', ServerMiddleware::class)
            ->addTag('babdev_websocket_server.server_middleware', ['priority' => 0]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'middleware.outer',
            0,
            new Reference('middleware.inner'),
        );

        $this->assertContainerBuilderHasAlias(ServerMiddleware::class, 'middleware.outer');
    }

    public function testAutowiredMiddlewareConfiguredWithAnAttributeIsAddedToTheStack(): void
    {
        // Autoconfigured services are only resolved by the optimization passes, which the test case container skips, so the full compiler is used here
        $container = new ContainerBuilder();
        $container->addCompilerPass(new BuildMiddlewareStackCompilerPass());

        $container->registerAttributeForAutoconfiguration(AsServerMiddleware::class, static function (ChildDefinition $definition, AsServerMiddleware $attribute): void {
            $definition->addTag('babdev_websocket_server.server_middleware', ['priority' => $attribute->priority]);
        });

        $container->register(AttributedServerMiddleware::class, AttributedServerMiddleware::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register('middleware.inner', ServerMiddleware::class)
            ->addTag('babdev_websocket_server.server_middleware', ['priority' => 0])
            ->setPublic(true);

        $container->register('middleware.consumer', \stdClass::class)
            ->addArgument(new Reference(ServerMiddleware::class))
            ->setPublic(true);

        $container->compile();

        self::assertThat(
            $container->getDefinition(AttributedServerMiddleware::class),
            new DefinitionHasArgumentConstraint(0, new Reference('middleware.inner'))
        );

        self::assertThat(
            $container->getDefinition('middleware.consumer'),
            new DefinitionHasArgumentConstraint(0, new Reference(AttributedServerMiddleware::class)),
            'The middleware stack should resolve to the outermost middleware.',
        );
    }

    public function testASingleMiddlewareIsAliasedAsTheStack(): void
    {
        $this->container->register('middleware.inner', ServerMiddleware::class)
            ->addTag('babdev_websocket_server.server_middleware', ['priority' => 0]);

        $this->compile();

        $this->assertContainerBuilderHasAlias(ServerMiddleware::class, 'middleware.inner');
    }

    public function testTheStackIsNotAliasedWhenThereIsNoMiddleware(): void
    {
        $this->compile();

        self::assertFalse($this->container->hasAlias(ServerMiddleware::class));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BuildMiddlewareStackCompilerPass());
    }
}
