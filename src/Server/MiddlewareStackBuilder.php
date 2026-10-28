<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Exception\MiddlewareNotConfigured;

/**
 * Builds the middleware stack that handles the connections to the websocket server and their messages.
 */
interface MiddlewareStackBuilder
{
    /**
     * @throws MiddlewareNotConfigured if the middleware stack is not properly configured
     */
    public function build(): ServerMiddleware;
}
