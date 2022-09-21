<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\PeriodicManager;

use BabDev\WebSocketBundle\Exception\MissingLoop;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class PingDoctrineDBALConnectionsPeriodicManager implements PeriodicManager, LoggerAwareInterface, ResetInterface
{
    use LoggerAwareTrait;

    private ?LoopInterface $loop = null;

    private ?TimerInterface $timer = null;

    /**
     * @param iterable<Connection> $connections
     */
    public function __construct(private readonly iterable $connections = [], private readonly int $interval = 60)
    {
    }

    public function getName(): string
    {
        return 'ping_doctrine_dbal_connections';
    }

    public function register(): void
    {
        // Wrap the entire loop in try/catch to prevent fatal errors crashing the websocket server
        try {
            $this->logger?->info('Registering ping doctrine/dbal connections manager.');

            if (null === $this->loop) {
                throw new MissingLoop(sprintf('The event loop has not been registered in %s', self::class));
            }

            $this->timer = $this->loop->addPeriodicTimer(
                $this->interval,
                $this->pingConnections(...),
            );
        } catch (MissingLoop $exception) {
            $this->logger?->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ],
            );
        } catch (\Throwable $exception) {
            $this->logger?->error(
                'Uncaught Throwable in the ping doctrine/dbal connections loop.',
                [
                    'exception' => $exception,
                ],
            );
        }
    }

    public function cancelTimers(): void
    {
        $this->reset();
    }

    public function setLoop(LoopInterface $loop): void
    {
        $this->loop = $loop;
    }

    /**
     * @internal
     */
    public function reset(): void
    {
        if (null === $this->timer) {
            return;
        }

        $this->loop?->cancelTimer($this->timer);

        $this->timer = null;
    }

    /**
     * @throws DBALException if the connection could not be pinged
     *
     * @internal
     */
    public function pingConnections(): void
    {
        $this->logger?->debug('Pinging all connections');

        foreach ($this->connections as $connection) {
            try {
                $startTime = microtime(true);

                $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());

                $endTime = microtime(true);

                $this->logger?->info(sprintf('Successfully pinged database server (~%s ms)', round(($endTime - $startTime) * 100000, 2)));
            } catch (DBALException $e) {
                $this->logger?->emergency(
                    'Could not ping database server',
                    [
                        'exception' => $e,
                    ]
                );

                throw $e;
            }
        }
    }
}
