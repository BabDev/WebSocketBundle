<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server;

use BabDev\WebSocketBundle\Server\DefaultSocketServerFactory;
use PHPUnit\Framework\TestCase;
use React\EventLoop\StreamSelectLoop;

final class DefaultSocketServerFactoryTest extends TestCase
{
    public function testCreatesASocketServerListeningOnTheUri(): void
    {
        // Port 0 lets the operating system choose an available port
        $socketServer = new DefaultSocketServerFactory([], new StreamSelectLoop())->build('127.0.0.1:0');

        try {
            self::assertStringStartsWith('tcp://127.0.0.1:', (string) $socketServer->getAddress());
        } finally {
            $socketServer->close();
        }
    }
}
