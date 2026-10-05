<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\PeriodicManager;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class PingDoctrineDBALConnectionsPeriodicManager implements PeriodicManager, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?LoopInterface $loop = null;

    private ?TimerInterface $timer = null;

    /**
     * @param iterable<Connection> $connections
     * @param positive-int         $interval
     */
    public function __construct(private readonly iterable $connections = [], private readonly int $interval = 60) {}

    public function getName(): string
    {
        return 'ping_doctrine_dbal_connections';
    }

    public function register(LoopInterface $loop): void
    {
        $this->loop = $loop;

        $this->logger?->info('Registering ping doctrine/dbal connections manager.');

        $this->timer = $this->loop->addPeriodicTimer(
            $this->interval,
            $this->pingConnections(...),
        );
    }

    public function cancelTimers(): void
    {
        if (!$this->timer instanceof TimerInterface) {
            return;
        }

        $this->loop?->cancelTimer($this->timer);

        $this->timer = null;
    }

    /**
     * @throws \Throwable if an unexpected error occurs while pinging a connection
     *
     * @internal
     */
    public function pingConnections(): void
    {
        $this->logger?->debug('Pinging all connections');

        foreach ($this->connections as $connection) {
            // This runs inside the event loop, so a database error must not escape or it will stop the websocket server; anything else is unexpected and is left to stop it
            try {
                $startTime = microtime(true);

                $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());

                $endTime = microtime(true);

                $this->logger?->info('Successfully pinged database server (~{time} ms)', ['time' => round(($endTime - $startTime) * 100000, 2)]);
            } catch (DBALException $e) {
                $this->logger?->emergency('Could not ping database server', ['exception' => $e]);

                // Closing the connection allows it to reconnect the next time it is used
                $connection->close();
            }
        }
    }
}
