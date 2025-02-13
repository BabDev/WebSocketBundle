<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

interface AuthenticationProvider
{
    /**
     * Checks to determine if this provider supports the given connection.
     */
    public function supports(Connection $connection): bool;

    /**
     * Attempts to authenticate the current connection.
     *
     * Implementations can assume this method will only be executed when {@see supports()} returns true.
     *
     * @throws AuthenticationException if there was an error while trying to authenticate the user
     */
    public function authenticate(Connection $connection): TokenInterface;
}
