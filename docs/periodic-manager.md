# Periodic Manager

The `BabDev\WebSocketBundle\PeriodicManager\PeriodicManager` interface represents a class which is used to dynamically manage periodic functions.

Periodic managers are initialized during the `BabDev\WebSocketBundle\Event\BeforeRunServer` event and the manager is responsible for registering its actions to the event loop.

The bundle will autoconfigure periodic managers with the `babdev_websocket_server.periodic_manager` service tag, which is required to ensure managers are correctly registered.

## Required Methods

### `getName()`

A manager must provide a unique name, these names are used in conjunction with the `BabDev\WebSocketBundle\PeriodicManager\PeriodicManagerRegistry`

### `register()`

The `register()` method is used to initialize the periodic manager using the provided event loop.

### `cancelTimers()`

The `cancelTimers()` method is called during the `BabDev\WebSocketBundle\Event\AfterServerClosed` event and allows for graceful shutdown of periodic functions owned by the manager.

## Example Manager

```php
<?php declare(strict_types=1);

namespace App\WebSocket\PeriodicManager;

use BabDev\WebSocketBundle\PeriodicManager\PeriodicManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\LoopInterface;

final class EchoPeriodicManager implements PeriodicManager, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?LoopInterface $loop = null;

    public function getName(): string
    {
        return 'echo'
    }

    public function register(LoopInterface $loop): void
    {
        $this->loop = $loop;

        // Wrap the entire loop in try/catch to prevent fatal errors crashing the websocket server
        try {
            // Register the timer to run every 15 seconds
            $this->loop->addPeriodicTimer(
                15,
                static function (): void {
                    echo 'This is a demo';
                },
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Uncaught Throwable in the echo manager.',
                [
                    'exception' => $exception,
                ],
            );
        }
    }

    public function cancelTimers(): void
    {
        // Nothing required for this manager
    }
}
```
