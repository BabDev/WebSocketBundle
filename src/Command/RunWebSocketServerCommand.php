<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Command;

use BabDev\WebSocketBundle\Server\ServerFactory;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'babdev:websocket-server:run', description: 'Runs the websocket server.')]
final class RunWebSocketServerCommand extends Command implements SignalableCommandInterface
{
    private SymfonyStyle $style;

    public function __construct(
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

        $this->serverFactory->build($uri)->run();

        return 0;
    }

    public function getSubscribedSignals(): array
    {
        return [\SIGINT, \SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        if (!\in_array($signal, [\SIGINT, \SIGTERM], true)) {
            return;
        }

        $this->loop->stop();

        $this->style->info('The websocket server has been stopped.');
    }
}
