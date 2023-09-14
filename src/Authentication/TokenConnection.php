<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class TokenConnection
{
    public function __construct(
        public readonly TokenInterface $token,
        public readonly Connection $connection,
    ) {}
}
