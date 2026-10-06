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
    public function testTheManagerIsRegistered(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);

        $loop->expects(self::once())
            ->method('addPeriodicTimer')
            ->willReturn(self::createStub(TimerInterface::class));

        $this->createManager()->register($loop);
    }

    public function testTheManagerPingsAllConnections(): void
    {
        $connections = $this->createConnections();

        foreach ($connections as $connection) {
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

        $this->createManager($connections)->pingConnections();
    }

    public function testAConnectionWhichCannotBePingedIsClosedWithoutStoppingTheOtherPings(): void
    {
        $logger = new TestLogger();

        $connections = $this->createConnections();

        $manager = $this->createManager($connections);
        $manager->setLogger($logger);

        foreach ($connections as $index => $connection) {
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

        $manager->pingConnections();

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

    public function testThePingDurationIsLoggedInMilliseconds(): void
    {
        $logger = new TestLogger();

        $manager = new PingDoctrineDBALConnectionsPeriodicManager([$connection = $this->createMock(Connection::class)]);
        $manager->setLogger($logger);

        $platform = self::createStub(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')
            ->willReturn('SELECT 1');

        // The ping is timed from before the platform is resolved, so a delay here is included in the logged duration
        $connection->expects(self::once())
            ->method('getDatabasePlatform')
            ->willReturnCallback(static function () use ($platform): AbstractPlatform {
                usleep(20_000);

                return $platform;
            });

        $connection->expects(self::once())
            ->method('executeQuery');

        $manager->pingConnections();

        self::assertTrue($logger->hasInfoThatPasses(static function (array $record): bool {
            if (!\is_string($record['message']) || !str_starts_with($record['message'], 'Successfully pinged database server') || !\is_array($record['context'])) {
                return false;
            }

            $time = $record['context']['time'] ?? null;

            return \is_float($time) && $time >= 15.0 && $time < 1000.0;
        }), 'The ping duration should be logged in milliseconds.');
    }

    public function testTheTimerIsCancelled(): void
    {
        $timer = self::createStub(TimerInterface::class);

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->method('addPeriodicTimer')
            ->willReturn($timer);

        $loop->expects(self::once())
            ->method('cancelTimer')
            ->with($timer);

        $manager = $this->createManager();
        $manager->register($loop);
        $manager->cancelTimers();

        // A second call has no timer left to cancel
        $manager->cancelTimers();
    }

    /**
     * @return list<MockObject&Connection>
     */
    private function createConnections(): array
    {
        return [
            $this->createMock(Connection::class),
            $this->createMock(Connection::class),
            $this->createMock(Connection::class),
        ];
    }

    /**
     * @param list<Connection> $connections
     */
    private function createManager(array $connections = []): PingDoctrineDBALConnectionsPeriodicManager
    {
        return new PingDoctrineDBALConnectionsPeriodicManager($connections);
    }
}
