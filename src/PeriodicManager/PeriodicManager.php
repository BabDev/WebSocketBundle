<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\PeriodicManager;

use React\EventLoop\LoopInterface;

/**
 * Adds timers to the websocket server's event loop when the server starts, and cancels them when it stops.
 */
interface PeriodicManager
{
    public function getName(): string;

    public function register(LoopInterface $loop): void;

    public function cancelTimers(): void;
}
