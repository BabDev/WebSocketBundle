<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\BuildMiddlewareStackCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
            'middleware.inner',
        );
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BuildMiddlewareStackCompilerPass());
    }
}
