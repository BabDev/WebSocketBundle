<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Command;

use BabDev\WebSocket\Server\Server;
use BabDev\WebSocketBundle\Command\RunWebSocketServerCommand;
use BabDev\WebSocketBundle\Server\ServerFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class RunWebSocketServerCommandTest extends TestCase
{
    public function testCommandLaunchesWebSocketServerWithUriFromConfiguration(): void
    {
        $uri = 'tcp://127.0.0.1:8080';

        /** @var MockObject&Server $server */
        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('run');

        /** @var MockObject&ServerFactory $serverFactory */
        $serverFactory = $this->createMock(ServerFactory::class);
        $serverFactory->expects(self::once())
            ->method('build')
            ->with($uri)
            ->willReturn($server);

        $command = new RunWebSocketServerCommand($serverFactory, $this->createMock(LoopInterface::class), $uri);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    public function testCommandLaunchesWebSocketServerWithUriFromArguments(): void
    {
        $uri = 'tcp://127.0.0.1:8081';

        /** @var MockObject&Server $server */
        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('run');

        /** @var MockObject&ServerFactory $serverFactory */
        $serverFactory = $this->createMock(ServerFactory::class);
        $serverFactory->expects(self::once())
            ->method('build')
            ->with($uri)
            ->willReturn($server);

        $command = new RunWebSocketServerCommand($serverFactory, $this->createMock(LoopInterface::class), 'tcp://127.0.0.1:8080');

        $commandTester = new CommandTester($command);
        $commandTester->execute(['uri' => $uri]);
    }
}
