<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\WAMP\Topic;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface ConnectionRepository
{
    /**
     * @return TokenConnection[]
     */
    public function findAll(Topic $topic, bool $anonymous = false): array;

    /**
     * @return TokenConnection[]
     */
    public function findAllByUsername(Topic $topic, string $username): array;

    /**
     * @return TokenConnection[]
     */
    public function findAllWithRoles(Topic $topic, array $roles): array;

    public function findTokenForConnection(Connection $connection): TokenInterface;

    public function getUser(Connection $connection): ?UserInterface;
}
