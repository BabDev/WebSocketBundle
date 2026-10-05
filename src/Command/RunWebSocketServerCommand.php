<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Command;

use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\Server;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocketBundle\Event\AfterLoopStopped;
use BabDev\WebSocketBundle\Event\AfterServerClosed;
use BabDev\WebSocketBundle\Event\BeforeRunServer;
use BabDev\WebSocketBundle\Server\ServerFactory;
use BabDev\WebSocketBundle\Server\SocketServerFactory;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(name: 'babdev:websocket-server:run', description: 'Runs the websocket server.')]
final class RunWebSocketServerCommand extends Command
{
    private SymfonyStyle $style;

    public function __construct(
        private readonly ?EventDispatcherInterface $eventDispatcher,
        private readonly SocketServerFactory $socketServerFactory,
        private readonly ServerFactory $serverFactory,
        private readonly LoopInterface $loop,
        private readonly string $uri,
        private readonly ?EstablishWebSocketConnection $webSocketMiddleware = null,
        private readonly ?float $shutdownTimeout = 5.0,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('uri', InputArgument::OPTIONAL, 'The URI to listen for connections on, defaults to the URI set in the bundle configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->style = new SymfonyStyle($input, $output);

        /** @var string $uri */
        $uri = $input->getArgument('uri') ?: $this->uri;

        $this->style->info(\sprintf('Launching websocket server, listening on "%s"', $uri));

        $socketServer = $this->socketServerFactory->build($uri);
        $server = $this->serverFactory->build($socketServer);

        // The SIG* constants require the pcntl extension
        $signals = [];

        foreach (['SIGINT', 'SIGTERM', 'SIGQUIT'] as $signalName) {
            if (\defined($signalName)) {
                /** @var int $signal */
                $signal = \constant($signalName);

                $signals[] = $signal;
            }
        }

        $closer = function () use (&$closer, $signals, $socketServer, $server): void {
            // Restore the default signal handling so a second signal stops the server immediately
            foreach ($signals as $signal) {
                $this->loop->removeSignal($signal, $closer);
            }

            $this->shutdownServer($socketServer, $server);
        };

        foreach ($signals as $signal) {
            $this->loop->addSignal($signal, $closer);
        }

        $this->eventDispatcher?->dispatch(new BeforeRunServer($socketServer, $this->loop));

        // The event loop runs until the server has shut down
        $server->run();

        $this->eventDispatcher?->dispatch(new AfterLoopStopped($socketServer, $this->loop));

        $this->style->info('The websocket server has been stopped.');

        return Command::SUCCESS;
    }

    private function shutdownServer(ServerInterface $socketServer, Server $server): void
    {
        $this->style->info('The websocket server is being stopped.');

        if ($server instanceof ReactPhpServer && null !== $this->shutdownTimeout) {
            // Clients are sent a "1001 Going Away" close frame, then the server stops the event loop once all connections have closed or the timeout is reached
            $this->webSocketMiddleware?->closeAllConnections();

            $server->shutdown($this->shutdownTimeout);
        } else {
            // The server is stopped immediately when graceful shutdown is disabled or not supported by the server
            $socketServer->close();

            $this->loop->stop();
        }

        $this->eventDispatcher?->dispatch(new AfterServerClosed($socketServer, $this->loop));
    }
}
