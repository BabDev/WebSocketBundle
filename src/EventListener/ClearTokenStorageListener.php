<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\EventListener;

use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Event\AfterServerClosed;

/**
 * @internal
 */
final class ClearTokenStorageListener
{
    public function __construct(private readonly TokenStorage $tokenStorage)
    {
    }

    public function __invoke(AfterServerClosed $event): void
    {
        $this->tokenStorage->removeAllTokens();
    }
}
