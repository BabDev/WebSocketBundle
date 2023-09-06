<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\PeriodicManager;

use BabDev\WebSocketBundle\PeriodicManager\PingDoctrineDBALConnectionsPeriodicManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class PingDoctrineDBALConnectionsPeriodicManagerTest extends TestCase
{
    /**
     * @var array<MockObject&Connection>
     */
    private readonly array $connections;

    private readonly TestLogger $logger;

    private readonly PingDoctrineDBALConnectionsPeriodicManager $manager;

    protected function setUp(): void
    {
        $this->connections = [
            $this->createMock(Connection::class),
            $this->createMock(Connection::class),
            $this->createMock(Connection::class),
        ];

        $this->logger = new TestLogger();

        $this->manager = new PingDoctrineDBALConnectionsPeriodicManager($this->connections);
        $this->manager->setLogger($this->logger);
    }

    protected function tearDown(): void
    {
        $this->manager->reset();

        parent::tearDown();
    }

    public function testTheManagerIsNotRegisteredWhenTheLoopIsMissing(): void
    {
        $this->manager->register();

        self::assertTrue($this->logger->hasErrorThatContains(sprintf('The event loop has not been registered in %s', PingDoctrineDBALConnectionsPeriodicManager::class)));
    }

    public function testTheManagerIsRegistered(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);

        $loop->expects(self::once())
            ->method('addPeriodicTimer')
            ->willReturn($this->createMock(TimerInterface::class));

        $this->manager->setLoop($loop);
        $this->manager->register();
    }

    public function testTheManagerPingsAllConnections(): void
    {
        foreach ($this->connections as $connection) {
            $query = 'SELECT 1';

            /** @var MockObject&AbstractPlatform $platform */
            $platform = $this->createMock(AbstractPlatform::class);
            $platform->expects(self::once())
                ->method('getDummySelectSQL')
                ->willReturn($query);

            $connection->expects(self::once())
                ->method('getDatabasePlatform')
                ->willReturn($platform);

            $connection->expects(self::once())
                ->method('executeQuery')
                ->with($query);
        }

        $this->manager->pingConnections();
    }
}
