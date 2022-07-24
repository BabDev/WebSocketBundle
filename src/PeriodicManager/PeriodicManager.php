<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\PeriodicManager;

use BabDev\WebSocketBundle\Exception\MissingLoop;
use React\EventLoop\LoopInterface;

interface PeriodicManager
{
    public function getName(): string;

    /**
     * @throws MissingLoop if called before setting the event loop
     */
    public function register(): void;

    public function cancelTimers(): void;

    public function setLoop(LoopInterface $loop): void;
}
