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
}
