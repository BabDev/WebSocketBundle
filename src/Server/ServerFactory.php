<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\Server;
use React\Socket\ServerInterface;

/**
 * Creates the websocket server, which runs the middleware stack on the connections of a socket server.
 */
interface ServerFactory
{
    public function build(ServerInterface $socket): Server;
}
