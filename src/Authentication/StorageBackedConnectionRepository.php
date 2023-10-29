<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class StorageBackedConnectionRepository implements ConnectionRepository
{
    public function __construct(
        private TokenStorage $tokenStorage,
        private Authenticator $authenticator,
    ) {}

    /**
     * @return list<TokenConnection>
     */
    public function findAll(Topic $topic, bool $anonymous = false): array
    {
        $result = [];

        /** @var Connection $connection */
        foreach ($topic as $connection) {
            $client = $this->findTokenForConnection($connection);

            if (!$anonymous && !($client->getUser() instanceof UserInterface)) {
                continue;
            }

            $result[] = new TokenConnection($client, $connection);
        }

        return $result;
    }

    /**
     * @return list<TokenConnection>
     */
    public function findAllByUsername(Topic $topic, string $username): array
    {
        $result = [];

        /** @var Connection $connection */
        foreach ($topic as $connection) {
            $client = $this->findTokenForConnection($connection);

            if ($client->getUserIdentifier() === $username) {
                $result[] = new TokenConnection($client, $connection);
            }
        }

        return $result;
    }

    /**
     * @return list<TokenConnection>
     */
    public function findAllWithRoles(Topic $topic, array $roles): array
    {
        $result = [];

        /** @var Connection $connection */
        foreach ($topic as $connection) {
            $client = $this->findTokenForConnection($connection);

            foreach ($client->getRoleNames() as $role) {
                if (\in_array($role, $roles, true)) {
                    $result[] = new TokenConnection($client, $connection);

                    continue 2;
                }
            }
        }

        return $result;
    }

    public function findTokenForConnection(Connection $connection): TokenInterface
    {
        $storageId = $this->tokenStorage->generateStorageId($connection);

        try {
            return $this->tokenStorage->getToken($storageId);
        } catch (TokenNotFound) {
            // Generally this would mean the token expired from storage, attempt to re-authenticate the connection
            $this->authenticator->authenticate($connection);

            return $this->findTokenForConnection($connection);
        }
    }

    public function getUser(Connection $connection): ?UserInterface
    {
        return $this->findTokenForConnection($connection)->getUser();
    }

    public function hasConnectionForUsername(Topic $topic, string $username): bool
    {
        /** @var Connection $connection */
        foreach ($topic as $connection) {
            $client = $this->findTokenForConnection($connection);

            if ($client->getUserIdentifier() === $username) {
                return true;
            }
        }

        return false;
    }
}
