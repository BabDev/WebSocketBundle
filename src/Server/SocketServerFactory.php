<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use React\Socket\ServerInterface;

/**
 * The socket server factory interface is responsible for creating the {@see ServerInterface} implementation
 * for the websocket server.
 */
interface SocketServerFactory
{
    public function build(string $uri): ServerInterface;
}
