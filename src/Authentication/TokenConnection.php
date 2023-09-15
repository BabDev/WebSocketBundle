<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final readonly class TokenConnection
{
    public function __construct(
        public TokenInterface $token,
        public Connection $connection,
    ) {}
}
