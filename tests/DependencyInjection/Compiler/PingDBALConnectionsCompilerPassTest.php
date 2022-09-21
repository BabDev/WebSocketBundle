<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocketBundle\DependencyInjection\Compiler\PingDBALConnectionsCompilerPass;
use BabDev\WebSocketBundle\PeriodicManager\PingDoctrineDBALConnectionsPeriodicManager;
use Doctrine\DBAL\Connection;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\ContainerHasParameterConstraint;
use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class PingDBALConnectionsCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testTagsDbalConnectionsToBePinged(): void
    {
        $this->container->register('babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections', PingDoctrineDBALConnectionsPeriodicManager::class);

        $this->container->register('database_connection', Connection::class);

        $this->container->setParameter('babdev_websocket_server.ping_dbal_connections', ['database_connection']);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag('database_connection', 'babdev_websocket_server.ping.dbal.connection');
        self::assertThat($this->container, new LogicalNot(new ContainerHasParameterConstraint('babdev_websocket_server.ping_dbal_connections', null, false)));
    }

    public function testThrowsAnExceptionIfAConnectionServiceIsNotConfigured(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "database_connection" service does not exist in the container, please review the "server.periodic.dbal.connections" configuration for the BabDevWebSocketBundle to ensure all connections are set in your DoctrineBundle configuration.');

        $this->container->register('babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections', PingDoctrineDBALConnectionsPeriodicManager::class);

        $this->container->setParameter('babdev_websocket_server.ping_dbal_connections', ['database_connection']);

        $this->compile();
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PingDBALConnectionsCompilerPass());
    }
}
