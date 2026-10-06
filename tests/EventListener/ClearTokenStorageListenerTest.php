<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\EventListener;

use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Event\AfterLoopStopped;
use BabDev\WebSocketBundle\EventListener\ClearTokenStorageListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;

final class ClearTokenStorageListenerTest extends TestCase
{
    public function testTheTokenStorageIsClearedAfterTheLoopIsStopped(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);
        $tokenStorage->expects(self::once())
            ->method('removeAllTokens');

        new ClearTokenStorageListener($tokenStorage)(new AfterLoopStopped(self::createStub(ServerInterface::class), self::createStub(LoopInterface::class)));
    }
}
