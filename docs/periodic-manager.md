# Periodic Manager

The `BabDev\WebSocketBundle\PeriodicManager\PeriodicManager` interface represents a class which is used to dynamically manage periodic functions.

Periodic managers are initialized during the `BabDev\WebSocketBundle\Event\BeforeRunServer` event and the manager is responsible for registering its actions to the event loop.

## Required Methods

### `getName()`

A manager must provide a unique name, these names are used in conjunction with the `BabDev\WebSocketBundle\PeriodicManager\PeriodicManagerRegistry`

### `setLoop()`

When managers are registered, they will be provided the event loop that is being used for the server process.

### `register()`

The `register()` method is used to initialize the periodic manager.

### `cancelTimers()`

The `cancelTimers()` method is called during the `BabDev\WebSocketBundle\Event\AfterServerClosed` event and allows for graceful shutdown of periodic functions owned by the manager.

## Example Manager

```php
<?php declare(strict_types=1);

namespace App\WebSocket\PeriodicManager;

use BabDev\WebSocketBundle\Exception\MissingLoop;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManager;
use React\EventLoop\LoopInterface;

final class EchoPeriodicManager implements PeriodicManager
{
    private ?LoopInterface $loop = null;

    public function getName(): string
    {
        return 'echo'
    }

    public function setLoop(LoopInterface $loop): void
    {
        $this->loop = $loop;
    }

    public function register(): void
    {
        // Wrap the entire loop in try/catch to prevent fatal errors crashing the websocket server
        try {
            if (null === $this->loop) {
                throw new MissingLoop(sprintf('The event loop has not been registered in %s', self::class));
            }

            // Register the timer to run every 15 seconds
            $this->loop->addPeriodicTimer(
                15,
                static function (): void {
                    echo 'This is a demo';
                },
            );
        } catch (MissingLoopException $exception) {
            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ],
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Uncaught Throwable in the pace calculator loop.',
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
