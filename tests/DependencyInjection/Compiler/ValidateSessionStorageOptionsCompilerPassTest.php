<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorageFactory;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\ValidateSessionStorageOptionsCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

final class ValidateSessionStorageOptionsCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testTheSessionOptionsAreRequiredWhenUsingASessionHandler(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "server.session.handler_service_id" option requires sessions to be enabled in the FrameworkBundle configuration ("framework.session")');

        $this->registerSessionStorageFactory();

        $this->compile();
    }

    public function testTheContainerCompilesWhenTheSessionOptionsAreSet(): void
    {
        $this->registerSessionStorageFactory();

        $this->container->setParameter('session.storage.options', ['name' => 'APPSESSID']);

        $this->compile();

        $this->assertContainerBuilderHasService('babdev_websocket_server.server.session.storage.factory.read_only_native');
    }

    public function testTheSessionOptionsAreNotRequiredWithoutASessionHandler(): void
    {
        $this->compile();

        $this->assertContainerBuilderNotHasService('babdev_websocket_server.server.session.storage.factory.read_only_native');
    }

    private function registerSessionStorageFactory(): void
    {
        $this->container->register('babdev_websocket_server.server.session.storage.factory.read_only_native', ReadOnlyNativeSessionStorageFactory::class);
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ValidateSessionStorageOptionsCompilerPass());
    }
}
