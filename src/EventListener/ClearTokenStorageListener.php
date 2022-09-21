<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\EventListener;

use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Event\AfterLoopStopped;

/**
 * @internal
 */
final class ClearTokenStorageListener
{
    public function __construct(private readonly TokenStorage $tokenStorage)
    {
    }

    public function __invoke(AfterLoopStopped $event): void
    {
        $this->tokenStorage->removeAllTokens();
    }
}
