<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Command;

use BabDev\WebSocket\Server\Server;
use BabDev\WebSocketBundle\Command\RunWebSocketServerCommand;
use BabDev\WebSocketBundle\Event\BeforeRunServer;
use BabDev\WebSocketBundle\Server\ServerFactory;
use BabDev\WebSocketBundle\Server\SocketServerFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RunWebSocketServerCommandTest extends TestCase
{
    public function testCommandLaunchesWebSocketServerWithUriFromConfiguration(): void
    {
        $uri = 'tcp://127.0.0.1:8080';

        /** @var MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(BeforeRunServer::class))
            ->willReturnArgument(0);

        /** @var Stub&ServerInterface $socketServer */
        $socketServer = self::createStub(ServerInterface::class);

        /** @var MockObject&Server $server */
        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('run');

        /** @var MockObject&SocketServerFactory $socketServerFactory */
        $socketServerFactory = $this->createMock(SocketServerFactory::class);
        $socketServerFactory->expects(self::once())
            ->method('build')
            ->with($uri)
            ->willReturn($socketServer);

        /** @var MockObject&ServerFactory $serverFactory */
        $serverFactory = $this->createMock(ServerFactory::class);
        $serverFactory->expects(self::once())
            ->method('build')
            ->with($socketServer)
            ->willReturn($server);

        $command = new RunWebSocketServerCommand($eventDispatcher, $socketServerFactory, $serverFactory, self::createStub(LoopInterface::class), $uri);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    public function testCommandLaunchesWebSocketServerWithUriFromArguments(): void
    {
        $uri = 'tcp://127.0.0.1:8081';

        /** @var MockObject&EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(BeforeRunServer::class))
            ->willReturnArgument(0);

        /** @var Stub&ServerInterface $socketServer */
        $socketServer = self::createStub(ServerInterface::class);

        /** @var MockObject&Server $server */
        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('run');

        /** @var MockObject&SocketServerFactory $socketServerFactory */
        $socketServerFactory = $this->createMock(SocketServerFactory::class);
        $socketServerFactory->expects(self::once())
            ->method('build')
            ->with($uri)
            ->willReturn($socketServer);

        /** @var MockObject&ServerFactory $serverFactory */
        $serverFactory = $this->createMock(ServerFactory::class);
        $serverFactory->expects(self::once())
            ->method('build')
            ->with($socketServer)
            ->willReturn($server);

        $command = new RunWebSocketServerCommand($eventDispatcher, $socketServerFactory, $serverFactory, self::createStub(LoopInterface::class), 'tcp://127.0.0.1:8080');

        $commandTester = new CommandTester($command);
        $commandTester->execute(['uri' => $uri]);
    }

    public function testCommandRegistersTheShutdownSignalsOnTheEventLoop(): void
    {
        $uri = 'tcp://127.0.0.1:8080';

        /** @var Stub&ServerInterface $socketServer */
        $socketServer = self::createStub(ServerInterface::class);

        /** @var MockObject&Server $server */
        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('run');

        /** @var MockObject&SocketServerFactory $socketServerFactory */
        $socketServerFactory = $this->createMock(SocketServerFactory::class);
        $socketServerFactory->method('build')
            ->with($uri)
            ->willReturn($socketServer);

        /** @var MockObject&ServerFactory $serverFactory */
        $serverFactory = $this->createMock(ServerFactory::class);
        $serverFactory->method('build')
            ->with($socketServer)
            ->willReturn($server);

        $registeredSignals = [];

        /** @var Stub&LoopInterface $loop */
        $loop = $this->createStub(LoopInterface::class);
        $loop->method('addSignal')
            ->willReturnCallback(function (int $signal) use (&$registeredSignals): void {
                $registeredSignals[] = $signal;
            });

        $command = new RunWebSocketServerCommand(null, $socketServerFactory, $serverFactory, $loop, $uri);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // The command guards each registration with \defined() since the SIG* constants
        // require ext-pcntl, so only assert on the signals available in this environment.
        $expectedSignals = array_values(array_filter(
            [
                \defined('SIGINT') ? \SIGINT : null,
                \defined('SIGTERM') ? \SIGTERM : null,
                \defined('SIGQUIT') ? \SIGQUIT : null,
            ],
            static fn (?int $signal): bool => null !== $signal,
        ));

        self::assertSame($expectedSignals, $registeredSignals);
    }
}
