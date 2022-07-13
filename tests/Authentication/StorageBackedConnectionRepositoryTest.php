<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocketBundle\Authentication\Authenticator;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Authentication\StorageBackedConnectionRepository;
use BabDev\WebSocketBundle\Authentication\TokenConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class StorageBackedConnectionRepositoryTest extends TestCase
{
    private readonly MockObject & TokenStorage $tokenStorage;

    private readonly MockObject & Authenticator $authenticator;

    private readonly StorageBackedConnectionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenStorage = $this->createMock(TokenStorage::class);
        $this->authenticator = $this->createMock(Authenticator::class);

        $this->repository = new StorageBackedConnectionRepository($this->tokenStorage, $this->authenticator);
    }

    public function testFindTokenForConnection(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $storageId = 42;

        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn((string) $storageId);

        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->with($storageId)
            ->willReturn($token);

        self::assertSame($token, $this->repository->findTokenForConnection($connection));
    }

    public function testFindTokenForConnectionAfterReauthenticating(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $storageId = 42;

        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);

        $this->tokenStorage->expects(self::exactly(2))
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn((string) $storageId);

        $this->tokenStorage->expects(self::exactly(2))
            ->method('getToken')
            ->with($storageId)
            ->willReturnOnConsecutiveCalls(
                self::throwException(new TokenNotFound()),
                $token
            );

        $this->authenticator->expects(self::once())
            ->method('authenticate')
            ->with($connection);

        self::assertSame($token, $this->repository->findTokenForConnection($connection));
    }

    public function testAllConnectionsForAUserCanBeFoundByUsername(): void
    {
        $usernameMethod = method_exists(AbstractToken::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername';

        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);

        $storageId1 = 42;
        $storageId2 = 43;
        $storageId3 = 44;

        $username1 = 'user';
        $username2 = 'guest';

        /** @var MockObject&AbstractToken $token1 */
        $token1 = $this->createMock(AbstractToken::class);
        $token1->expects(self::once())
            ->method($usernameMethod)
            ->willReturn($username1);

        /** @var MockObject&AbstractToken $token2 */
        $token2 = $this->createMock(AbstractToken::class);
        $token2->expects(self::once())
            ->method($usernameMethod)
            ->willReturn($username1);

        /** @var MockObject&AbstractToken $token3 */
        $token3 = $this->createMock(AbstractToken::class);
        $token3->expects(self::once())
            ->method($usernameMethod)
            ->willReturn($username2);

        $this->tokenStorage->expects(self::exactly(3))
            ->method('generateStorageId')
            ->withConsecutive(
                [$connection1],
                [$connection2],
                [$connection3]
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2,
                (string) $storageId3
            );

        $this->tokenStorage->expects(self::exactly(3))
            ->method('getToken')
            ->withConsecutive(
                [$storageId1],
                [$storageId2],
                [$storageId3]
            )
            ->willReturnOnConsecutiveCalls(
                $token1,
                $token2,
                $token3
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);
        $topic->add($connection3);

        self::assertEquals(
            [
                new TokenConnection($token1, $connection1),
                new TokenConnection($token2, $connection2),
            ],
            $this->repository->findAllByUsername($topic, $username1)
        );
    }

    public function testFetchingAllConnectionsByDefaultOnlyReturnsAuthenticatedUsers(): void
    {
        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);

        $storageId1 = 42;
        $storageId2 = 84;

        /** @var MockObject&TokenInterface $authenticatedToken */
        $authenticatedToken = $this->createMock(TokenInterface::class);
        $authenticatedToken->expects(self::once())
            ->method('getUser')
            ->willReturn($this->createMock(UserInterface::class));

        /** @var MockObject&TokenInterface $guestToken */
        $guestToken = $this->createMock(TokenInterface::class);
        $guestToken->expects(self::once())
            ->method('getUser')
            ->willReturn(null);

        $this->tokenStorage->expects(self::exactly(2))
            ->method('generateStorageId')
            ->withConsecutive(
                [$connection1],
                [$connection2]
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2
            );

        $this->tokenStorage->expects(self::exactly(2))
            ->method('getToken')
            ->withConsecutive(
                [$storageId1],
                [$storageId2]
            )
            ->willReturnOnConsecutiveCalls(
                $authenticatedToken,
                $guestToken
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);

        self::assertEquals(
            [
                new TokenConnection($authenticatedToken, $connection1),
            ],
            $this->repository->findAll($topic)
        );
    }

    public function testFetchingAllConnectionsWithAnonymousFlagReturnsAllConnectedUsers(): void
    {
        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);

        $storageId1 = 42;
        $storageId2 = 84;

        /** @var MockObject&TokenInterface $authenticatedToken */
        $authenticatedToken = $this->createMock(TokenInterface::class);
        $authenticatedToken->expects(self::never())
            ->method('getUser');

        /** @var MockObject&TokenInterface $guestToken */
        $guestToken = $this->createMock(TokenInterface::class);
        $guestToken->expects(self::never())
            ->method('getUser');

        $this->tokenStorage->expects(self::exactly(2))
            ->method('generateStorageId')
            ->withConsecutive(
                [$connection1],
                [$connection2]
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2
            );

        $this->tokenStorage->expects(self::exactly(2))
            ->method('getToken')
            ->withConsecutive(
                [$storageId1],
                [$storageId2]
            )
            ->willReturnOnConsecutiveCalls(
                $authenticatedToken,
                $guestToken
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);

        self::assertEquals(
            [
                new TokenConnection($authenticatedToken, $connection1),
                new TokenConnection($guestToken, $connection2),
            ],
            $this->repository->findAll($topic, true)
        );
    }

    public function testFetchingAllUsersWithDefinedRolesOnlyReturnsMatchingUsers(): void
    {
        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);

        $storageId1 = 42;
        $storageId2 = 84;
        $storageId3 = 126;

        /** @var MockObject&TokenInterface $authenticatedToken1 */
        $authenticatedToken1 = $this->createMock(TokenInterface::class);
        $authenticatedToken1->expects(self::once())
            ->method('getRoleNames')
            ->willReturn(['ROLE_USER', 'ROLE_STAFF']);

        /** @var MockObject&TokenInterface $authenticatedToken2 */
        $authenticatedToken2 = $this->createMock(TokenInterface::class);
        $authenticatedToken2->expects(self::once())
            ->method('getRoleNames')
            ->willReturn(['ROLE_USER']);

        /** @var MockObject&TokenInterface $guestToken */
        $guestToken = $this->createMock(TokenInterface::class);
        $guestToken->expects(self::once())
            ->method('getRoleNames')
            ->willReturn([]);

        $this->tokenStorage->expects(self::exactly(3))
            ->method('generateStorageId')
            ->withConsecutive(
                [$connection1],
                [$connection2],
                [$connection3]
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2,
                (string) $storageId3
            );

        $this->tokenStorage->expects(self::exactly(3))
            ->method('getToken')
            ->withConsecutive(
                [$storageId1],
                [$storageId2],
                [$storageId3]
            )
            ->willReturnOnConsecutiveCalls(
                $authenticatedToken1,
                $authenticatedToken2,
                $guestToken
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);
        $topic->add($connection3);

        self::assertEquals(
            [
                new TokenConnection($authenticatedToken1, $connection1),
            ],
            $this->repository->findAllWithRoles($topic, ['ROLE_STAFF'])
        );
    }
}
