<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\StorageError;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class StorageBackedConnectionRepository implements ConnectionRepository
{
    public function __construct(
        private TokenStorage $tokenStorage,
        private Authenticator $authenticator,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return list<TokenConnection>
     */
    public function findAll(Topic $topic, bool $anonymous = false): array
    {
        $result = [];

        foreach ($topic as $connection) {
            $client = $this->findTokenForTopicConnection($connection);

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

        foreach ($topic as $connection) {
            $client = $this->findTokenForTopicConnection($connection);

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

        foreach ($topic as $connection) {
            $client = $this->findTokenForTopicConnection($connection);

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

            return $this->tokenStorage->getToken($storageId);
        }
    }

    public function getUser(Connection $connection): ?UserInterface
    {
        return $this->findTokenForConnection($connection)->getUser();
    }

    public function hasConnectionForUsername(Topic $topic, string $username): bool
    {
        foreach ($topic as $connection) {
            $client = $this->findTokenForTopicConnection($connection);

            if ($client->getUserIdentifier() === $username) {
                return true;
            }
        }

        return false;
    }

    private function findTokenForTopicConnection(Connection $connection): TokenInterface
    {
        try {
            return $this->findTokenForConnection($connection);
        } catch (AuthenticationException|StorageError|TokenNotFound $exception) {
            $this->logger?->warning(
                'Could not find the token for a connection, it will be treated as an anonymous user.',
                [
                    'exception' => $exception,
                    'resource_id' => $connection->getAttributeStore()->get(AttributeKey::RESOURCE_ID),
                ],
            );

            return new NullToken();
        }
    }
}
