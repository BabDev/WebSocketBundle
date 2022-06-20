<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\ServerMiddleware;

interface MiddlewareStackBuilder
{
    public function build(): ServerMiddleware;
}
