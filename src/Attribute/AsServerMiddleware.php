<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Attribute;

use BabDev\WebSocket\Server\ServerMiddleware;

/**
 * Attribute used to configure {@see ServerMiddleware}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsServerMiddleware
{
    public function __construct(
        public int $priority,
    ) {}
}
