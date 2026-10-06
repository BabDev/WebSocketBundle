<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\EventListener;

use BabDev\WebSocketBundle\Event\AfterServerClosed;
use BabDev\WebSocketBundle\Event\BeforeRunServer;
use BabDev\WebSocketBundle\EventListener\PeriodicManagerSubscriber;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManager;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

final class PeriodicManagerSubscriberTest extends TestCase
{
    public function testCancelsPeriodicManagers(): void
    {
        /** @var Stub&LoopInterface $loop */
        $loop = self::createStub(LoopInterface::class);

        /** @var Stub&ServerInterface $server */
        $server = self::createStub(ServerInterface::class);

        /** @var MockObject&PeriodicManager $manager */
        $manager = $this->createMock(PeriodicManager::class);
        $manager->expects(self::once())
            ->method('cancelTimers');

        $registry = self::createStub(PeriodicManagerRegistry::class);
        $registry->method('getManagers')
            ->willReturn(['test' => $manager]);

        new PeriodicManagerSubscriber($registry)->onAfterServerClosed(new AfterServerClosed($server, $loop));
    }

    public function testInitializesPeriodicManagers(): void
    {
        /** @var Stub&LoopInterface $loop */
        $loop = self::createStub(LoopInterface::class);

        /** @var Stub&ServerInterface $server */
        $server = self::createStub(ServerInterface::class);

        /** @var MockObject&PeriodicManager $manager */
        $manager = $this->createMock(PeriodicManager::class);
        $manager->expects(self::once())
            ->method('register')
            ->with($loop);

        $registry = self::createStub(PeriodicManagerRegistry::class);
        $registry->method('getManagers')
            ->willReturn(['test' => $manager]);

        new PeriodicManagerSubscriber($registry)->onBeforeRunServer(new BeforeRunServer($server, $loop));
    }
}
