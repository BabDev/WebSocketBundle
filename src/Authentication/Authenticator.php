<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;

interface Authenticator
{
    /**
     * Attempts to authenticate the current connection.
     */
    public function authenticate(Connection $connection): void;
}
