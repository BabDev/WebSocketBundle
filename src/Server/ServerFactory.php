<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\Server;

interface ServerFactory
{
    public function build(string $uri): Server;
}
