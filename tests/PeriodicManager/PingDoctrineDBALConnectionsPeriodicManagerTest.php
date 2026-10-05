<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\PeriodicManager;

use BabDev\WebSocketBundle\PeriodicManager\PingDoctrineDBALConnectionsPeriodicManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException as DBALInvalidArgumentException;
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

    private readonly PingDoctrineDBALConnectionsPeriodicManager $manager;

    protected function setUp(): void
    {
        $this->connections = [
            $this->createMock(Connection::class),
            $this->createMock(Connection::class),
            $this->createMock(Connection::class),
        ];

        $this->manager = new PingDoctrineDBALConnectionsPeriodicManager($this->connections);
        $this->manager->setLogger(new TestLogger());
    }

    protected function tearDown(): void
    {
        $this->manager->cancelTimers();

        parent::tearDown();
    }

    public function testTheManagerIsRegistered(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);

        $loop->expects(self::once())
            ->method('addPeriodicTimer')
            ->willReturn(self::createStub(TimerInterface::class));

        $this->manager->register($loop);
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

    public function testAConnectionWhichCannotBePingedIsClosedWithoutStoppingTheOtherPings(): void
    {
        $logger = new TestLogger();

        $this->manager->setLogger($logger);

        foreach ($this->connections as $index => $connection) {
            $platform = self::createStub(AbstractPlatform::class);
            $platform->method('getDummySelectSQL')
                ->willReturn('SELECT 1');

            $connection->method('getDatabasePlatform')
                ->willReturn($platform);

            if (0 === $index) {
                $connection->expects(self::once())
                    ->method('executeQuery')
                    ->willThrowException(new DBALInvalidArgumentException('Server has gone away'));

                $connection->expects(self::once())
                    ->method('close');
            } else {
                $connection->expects(self::once())
                    ->method('executeQuery');

                $connection->expects(self::never())
                    ->method('close');
            }
        }

        $this->manager->pingConnections();

        self::assertTrue($logger->hasEmergencyThatContains('Could not ping database server'));
    }

    public function testAnUnexpectedErrorWhilePingingIsNotCaught(): void
    {
        $platform = self::createStub(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')
            ->willReturn('SELECT 1');

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection->expects(self::once())
            ->method('executeQuery')
            ->willThrowException(new \LogicException('Something unexpected'));

        $connection->expects(self::never())
            ->method('close');

        $this->expectException(\LogicException::class);

        new PingDoctrineDBALConnectionsPeriodicManager([$connection])->pingConnections();
    }
}
