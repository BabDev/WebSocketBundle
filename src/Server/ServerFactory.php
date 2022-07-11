<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\Server;
use React\Socket\ServerInterface;

/**
 * The server factory creates the {@see Server} instance powering the websocket server using a
 * {@see ServerInterface} implementation.
 */
interface ServerFactory
{
    public function build(ServerInterface $socket): Server;
}
