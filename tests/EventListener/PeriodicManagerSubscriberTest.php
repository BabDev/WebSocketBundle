<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\EventListener;

use BabDev\WebSocketBundle\Event\AfterServerClosed;
use BabDev\WebSocketBundle\Event\BeforeRunServer;
use BabDev\WebSocketBundle\EventListener\PeriodicManagerSubscriber;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManager;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

final class PeriodicManagerSubscriberTest extends TestCase
{
    private readonly MockObject&PeriodicManagerRegistry $registry;

    private readonly PeriodicManagerSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->createMock(PeriodicManagerRegistry::class);

        $this->subscriber = new PeriodicManagerSubscriber($this->registry);
    }

    public function testCancelsPeriodicManagers(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);

        /** @var MockObject&ServerInterface $server */
        $server = $this->createMock(ServerInterface::class);

        /** @var MockObject&PeriodicManager $manager */
        $manager = $this->createMock(PeriodicManager::class);
        $manager->expects(self::once())
            ->method('cancelTimers');

        $this->registry->method('getManagers')
            ->willReturn(['test' => $manager]);

        $this->subscriber->onAfterServerClosed(new AfterServerClosed($server, $loop));
    }

    public function testInitializesPeriodicManagers(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);

        /** @var MockObject&ServerInterface $server */
        $server = $this->createMock(ServerInterface::class);

        /** @var MockObject&PeriodicManager $manager */
        $manager = $this->createMock(PeriodicManager::class);
        $manager->expects(self::once())
            ->method('setLoop')
            ->with($loop);

        $manager->expects(self::once())
            ->method('register');

        $this->registry->method('getManagers')
            ->willReturn(['test' => $manager]);

        $this->subscriber->onBeforeRunServer(new BeforeRunServer($server, $loop));
    }
}
