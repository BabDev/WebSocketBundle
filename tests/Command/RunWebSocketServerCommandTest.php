<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Command;

use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\Server;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocketBundle\Command\RunWebSocketServerCommand;
use BabDev\WebSocketBundle\Event\AfterLoopStopped;
use BabDev\WebSocketBundle\Event\AfterServerClosed;
use BabDev\WebSocketBundle\Event\BeforeRunServer;
use BabDev\WebSocketBundle\Server\ServerFactory;
use BabDev\WebSocketBundle\Server\SocketServerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
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
        $eventDispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::logicalOr(self::isInstanceOf(BeforeRunServer::class), self::isInstanceOf(AfterLoopStopped::class)))
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
        $eventDispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::logicalOr(self::isInstanceOf(BeforeRunServer::class), self::isInstanceOf(AfterLoopStopped::class)))
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

        $registeredSignals = [];

        /** @var Stub&LoopInterface $loop */
        $loop = self::createStub(LoopInterface::class);
        $loop->method('addSignal')
            ->willReturnCallback(static function (int $signal) use (&$registeredSignals): void {
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

        self::assertArraysAreIdentical($expectedSignals, $registeredSignals);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testTheServerIsShutDownGracefullyWhenAShutdownSignalIsReceived(): void
    {
        $uri = 'tcp://127.0.0.1:8080';

        /** @var MockObject&ServerInterface $socketServer */
        $socketServer = $this->createMock(ServerInterface::class);
        $socketServer->expects(self::once())
            ->method('close');

        /** @var array<int, callable> $signalHandlers */
        $signalHandlers = [];

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->method('addSignal')
            ->willReturnCallback(static function (int $signal, callable $listener) use (&$signalHandlers): void {
                $signalHandlers[$signal] = $listener;
            });

        // A signal is received while the server is running
        $loop->expects(self::once())
            ->method('run')
            ->willReturnCallback(static function () use (&$signalHandlers): void {
                $signalHandlers[\SIGTERM]();
            });

        $removedSignals = [];

        $loop->expects(self::exactly(3))
            ->method('removeSignal')
            ->willReturnCallback(static function (int $signal, callable $listener) use (&$signalHandlers, &$removedSignals): void {
                self::assertSame($signalHandlers[$signal], $listener);

                $removedSignals[] = $signal;
            });

        // With no open connections, the server stops the event loop as soon as it shuts down
        $loop->expects(self::once())
            ->method('stop');

        $server = new ReactPhpServer(self::createStub(ServerMiddleware::class), $socketServer, $loop);

        $socketServerFactory = self::createStub(SocketServerFactory::class);
        $socketServerFactory->method('build')
            ->willReturn($socketServer);

        $serverFactory = self::createStub(ServerFactory::class);
        $serverFactory->method('build')
            ->willReturn($server);

        $dispatchedEvents = [];

        $eventDispatcher = self::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): object {
                $dispatchedEvents[] = $event::class;

                return $event;
            });

        $command = new RunWebSocketServerCommand(
            $eventDispatcher,
            $socketServerFactory,
            $serverFactory,
            $loop,
            $uri,
            new EstablishWebSocketConnection(self::createStub(ServerMiddleware::class)),
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertArraysAreIdentical([\SIGINT, \SIGTERM, \SIGQUIT], $removedSignals, 'All signal handlers should be removed so a second signal stops the server immediately.');
        self::assertArraysAreIdentical([BeforeRunServer::class, AfterServerClosed::class, AfterLoopStopped::class], $dispatchedEvents);
        self::assertStringContainsString('The websocket server has been stopped.', $commandTester->getDisplay());
    }

    #[RequiresPhpExtension('pcntl')]
    public function testAServerWithoutGracefulShutdownSupportIsStoppedWhenAShutdownSignalIsReceived(): void
    {
        /** @var MockObject&ServerInterface $socketServer */
        $socketServer = $this->createMock(ServerInterface::class);
        $socketServer->expects(self::once())
            ->method('close');

        /** @var array<int, callable> $signalHandlers */
        $signalHandlers = [];

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->method('addSignal')
            ->willReturnCallback(static function (int $signal, callable $listener) use (&$signalHandlers): void {
                $signalHandlers[$signal] = $listener;
            });

        $loop->expects(self::once())
            ->method('stop');

        /** @var MockObject&Server $server */
        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('run')
            ->willReturnCallback(static function () use (&$signalHandlers): void {
                $signalHandlers[\SIGINT]();
            });

        $socketServerFactory = self::createStub(SocketServerFactory::class);
        $socketServerFactory->method('build')
            ->willReturn($socketServer);

        $serverFactory = self::createStub(ServerFactory::class);
        $serverFactory->method('build')
            ->willReturn($server);

        $dispatchedEvents = [];

        $eventDispatcher = self::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): object {
                $dispatchedEvents[] = $event::class;

                return $event;
            });

        $command = new RunWebSocketServerCommand($eventDispatcher, $socketServerFactory, $serverFactory, $loop, 'tcp://127.0.0.1:8080');

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertArraysAreIdentical([BeforeRunServer::class, AfterServerClosed::class, AfterLoopStopped::class], $dispatchedEvents);
    }

    /**
     * @return iterable<string, array{float|null}>
     */
    public static function shutdownTimeouts(): iterable
    {
        yield 'graceful shutdown' => [2.5];
        yield 'immediate shutdown' => [null];
    }

    #[DataProvider('shutdownTimeouts')]
    #[RequiresPhpExtension('pcntl')]
    public function testTheShutdownTimeoutControlsHowOpenConnectionsAreClosed(?float $shutdownTimeout): void
    {
        /** @var MockObject&ServerInterface $socketServer */
        $socketServer = $this->createMock(ServerInterface::class);
        $socketServer->expects(self::once())
            ->method('close');

        /** @var array<int, callable> $signalHandlers */
        $signalHandlers = [];

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->method('addSignal')
            ->willReturnCallback(static function (int $signal, callable $listener) use (&$signalHandlers): void {
                $signalHandlers[$signal] = $listener;
            });

        $server = new ReactPhpServer(self::createStub(ServerMiddleware::class), $socketServer, $loop);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);

        $loop->expects(self::once())
            ->method('run')
            ->willReturnCallback(static function () use ($server, $connection, &$signalHandlers): void {
                $server->onConnection($connection);

                $signalHandlers[\SIGTERM]();
            });

        if (null !== $shutdownTimeout) {
            // The server waits for the open connection to close, up to the shutdown timeout
            $connection->expects(self::once())
                ->method('end');

            $loop->expects(self::once())
                ->method('addTimer')
                ->with($shutdownTimeout)
                ->willReturn(self::createStub(TimerInterface::class));

            $loop->expects(self::never())
                ->method('stop');
        } else {
            $connection->expects(self::never())
                ->method('end');

            $loop->expects(self::never())
                ->method('addTimer');

            $loop->expects(self::once())
                ->method('stop');
        }

        $socketServerFactory = self::createStub(SocketServerFactory::class);
        $socketServerFactory->method('build')
            ->willReturn($socketServer);

        $serverFactory = self::createStub(ServerFactory::class);
        $serverFactory->method('build')
            ->willReturn($server);

        $command = new RunWebSocketServerCommand(
            null,
            $socketServerFactory,
            $serverFactory,
            $loop,
            'tcp://127.0.0.1:8080',
            new EstablishWebSocketConnection(self::createStub(ServerMiddleware::class)),
            $shutdownTimeout,
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }
}
