<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Attribute;

use BabDev\WebSocket\Server\ServerMiddleware;

/**
 * Registers a {@see ServerMiddleware} as a service and adds it to the middleware stack of the websocket server.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsServerMiddleware
{
    public function __construct(
        public int $priority,
    ) {}
}
