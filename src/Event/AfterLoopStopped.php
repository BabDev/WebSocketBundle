<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Event;

use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterLoopStopped extends Event
{
    public function __construct(
        public readonly ServerInterface $socketServer,
        public readonly LoopInterface $loop,
    ) {}
}
