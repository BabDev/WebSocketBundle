<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use React\Socket\ServerInterface;

/**
 * Creates the socket server that listens for connections to the websocket server on a URI.
 */
interface SocketServerFactory
{
    public function build(string $uri): ServerInterface;
}
