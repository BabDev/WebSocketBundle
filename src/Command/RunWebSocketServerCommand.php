<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Command;

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

        $this->style->info(sprintf('Launching websocket server, listening on "%s"', $uri));

        $socketServer = $this->socketServerFactory->build($uri);
        $server = $this->serverFactory->build($socketServer);

        $closer = function () use ($socketServer): void {
            $this->shutdownServer($socketServer);
        };

        if (\defined('SIGINT')) {
            $this->loop->addSignal(\SIGINT, $closer);
        }

        if (\defined('SIGTERM')) {
            $this->loop->addSignal(\SIGTERM, $closer);
        }

        $this->eventDispatcher?->dispatch(new BeforeRunServer($socketServer, $this->loop));

        $server->run();

        return Command::SUCCESS;
    }

    /**
     * @internal
     */
    public function shutdownServer(ServerInterface $socketServer): void
    {
        $this->style->info('The websocket server is being stopped.');

        $socketServer->emit('end');
        $socketServer->close();

        $this->eventDispatcher?->dispatch(new AfterServerClosed($socketServer, $this->loop));

        $this->loop->stop();

        $this->eventDispatcher?->dispatch(new AfterLoopStopped($socketServer, $this->loop));

        $this->style->info('The websocket server has been stopped.');
    }
}
