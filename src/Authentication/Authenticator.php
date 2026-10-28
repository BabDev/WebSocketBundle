<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;

/**
 * Authenticates a connection to the websocket server and stores the resulting security token for it.
 */
interface Authenticator
{
    /**
     * Attempts to authenticate the current connection.
     *
     * @throws AuthenticationException if there was an error while trying to authenticate the user
     */
    public function authenticate(Connection $connection): void;
}
