<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Exception\MiddlewareNotConfigured;

interface MiddlewareStackBuilder
{
    /**
     * @throws MiddlewareNotConfigured if the middleware stack is not properly configured
     */
    public function build(): ServerMiddleware;
}
