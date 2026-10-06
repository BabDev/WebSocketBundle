<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocketBundle\Authentication\Authenticator;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Storage\Driver\InMemoryStorageDriver;
use BabDev\WebSocketBundle\Authentication\Storage\DriverBackedTokenStorage;
use BabDev\WebSocketBundle\Authentication\Storage\Exception\TokenNotFound;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Authentication\StorageBackedConnectionRepository;
use BabDev\WebSocketBundle\Authentication\TokenConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class StorageBackedConnectionRepositoryTest extends TestCase
{
    public function testFindTokenForConnection(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $repository = $this->createRepository(tokenStorage: $tokenStorage);

        /** @var Stub&Connection $connection */
        $connection = self::createStub(Connection::class);

        $storageId = 42;

        /** @var Stub&TokenInterface $token */
        $token = self::createStub(TokenInterface::class);

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn((string) $storageId);

        $tokenStorage->expects(self::once())
            ->method('getToken')
            ->with($storageId)
            ->willReturn($token);

        self::assertSame($token, $repository->findTokenForConnection($connection));
    }

    public function testFindTokenForConnectionAfterReauthenticating(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        /** @var MockObject&Authenticator $authenticator */
        $authenticator = $this->createMock(Authenticator::class);

        $repository = $this->createRepository(tokenStorage: $tokenStorage, authenticator: $authenticator);

        /** @var Stub&Connection $connection */
        $connection = self::createStub(Connection::class);

        $storageId = 42;

        /** @var Stub&TokenInterface $token */
        $token = self::createStub(TokenInterface::class);

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn((string) $storageId);

        $tokenStorage->expects(self::exactly(2))
            ->method('getToken')
            ->with($storageId)
            ->willReturnOnConsecutiveCalls(
                self::throwException(new TokenNotFound()),
                $token
            );

        $authenticator->expects(self::once())
            ->method('authenticate')
            ->with($connection);

        self::assertSame($token, $repository->findTokenForConnection($connection));
    }

    public function testAllConnectionsForAUserCanBeFoundByUsername(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $repository = $this->createRepository(tokenStorage: $tokenStorage);

        /** @var Stub&WAMPConnection $connection1 */
        $connection1 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection2 */
        $connection2 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection3 */
        $connection3 = self::createStub(WAMPConnection::class);

        $storageId1 = 42;
        $storageId2 = 43;
        $storageId3 = 44;

        $username1 = 'user';
        $username2 = 'guest';

        /** @var MockObject&TokenInterface $token1 */
        $token1 = $this->createMock(TokenInterface::class);
        $token1->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn($username1);

        /** @var MockObject&TokenInterface $token2 */
        $token2 = $this->createMock(TokenInterface::class);
        $token2->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn($username1);

        /** @var MockObject&TokenInterface $token3 */
        $token3 = $this->createMock(TokenInterface::class);
        $token3->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn($username2);

        $tokenStorage->expects(self::exactly(3))
            ->method('generateStorageId')
            ->withParameterSetsInOrder(
                self::identicalTo($connection1),
                self::identicalTo($connection2),
                self::identicalTo($connection3),
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2,
                (string) $storageId3,
            );

        $tokenStorage->expects(self::exactly(3))
            ->method('getToken')
            ->withParameterSetsInOrder(
                self::identicalTo((string) $storageId1),
                self::identicalTo((string) $storageId2),
                self::identicalTo((string) $storageId3),
            )
            ->willReturnOnConsecutiveCalls(
                $token1,
                $token2,
                $token3,
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);
        $topic->add($connection3);

        self::assertEquals([
            new TokenConnection($token1, $connection1),
            new TokenConnection($token2, $connection2),
        ], $repository->findAllByUsername($topic, $username1));
    }

    public function testFetchingAllConnectionsByDefaultOnlyReturnsAuthenticatedUsers(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $repository = $this->createRepository(tokenStorage: $tokenStorage);

        /** @var Stub&WAMPConnection $connection1 */
        $connection1 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection2 */
        $connection2 = self::createStub(WAMPConnection::class);

        $storageId1 = 42;
        $storageId2 = 84;

        /** @var MockObject&TokenInterface $authenticatedToken */
        $authenticatedToken = $this->createMock(TokenInterface::class);
        $authenticatedToken->expects(self::once())
            ->method('getUser')
            ->willReturn(self::createStub(UserInterface::class));

        /** @var MockObject&TokenInterface $guestToken */
        $guestToken = $this->createMock(TokenInterface::class);
        $guestToken->expects(self::once())
            ->method('getUser')
            ->willReturn(null);

        $tokenStorage->expects(self::exactly(2))
            ->method('generateStorageId')
            ->withParameterSetsInOrder(
                self::identicalTo($connection1),
                self::identicalTo($connection2),
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2,
            );

        $tokenStorage->expects(self::exactly(2))
            ->method('getToken')
            ->withParameterSetsInOrder(
                self::identicalTo((string) $storageId1),
                self::identicalTo((string) $storageId2),
            )
            ->willReturnOnConsecutiveCalls(
                $authenticatedToken,
                $guestToken,
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);

        self::assertEquals([
            new TokenConnection($authenticatedToken, $connection1),
        ], $repository->findAll($topic));
    }

    public function testFetchingAllConnectionsWithAnonymousFlagReturnsAllConnectedUsers(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $repository = $this->createRepository(tokenStorage: $tokenStorage);

        /** @var Stub&WAMPConnection $connection1 */
        $connection1 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection2 */
        $connection2 = self::createStub(WAMPConnection::class);

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

        $tokenStorage->expects(self::exactly(2))
            ->method('generateStorageId')
            ->withParameterSetsInOrder(
                self::identicalTo($connection1),
                self::identicalTo($connection2),
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2,
            );

        $tokenStorage->expects(self::exactly(2))
            ->method('getToken')
            ->withParameterSetsInOrder(
                self::identicalTo((string) $storageId1),
                self::identicalTo((string) $storageId2),
            )
            ->willReturnOnConsecutiveCalls(
                $authenticatedToken,
                $guestToken,
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);

        self::assertEquals([
            new TokenConnection($authenticatedToken, $connection1),
            new TokenConnection($guestToken, $connection2),
        ], $repository->findAll($topic, true));
    }

    public function testFetchingAllUsersWithDefinedRolesOnlyReturnsMatchingUsers(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $repository = $this->createRepository(tokenStorage: $tokenStorage);

        /** @var Stub&WAMPConnection $connection1 */
        $connection1 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection2 */
        $connection2 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection3 */
        $connection3 = self::createStub(WAMPConnection::class);

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

        $tokenStorage->expects(self::exactly(3))
            ->method('generateStorageId')
            ->withParameterSetsInOrder(
                self::identicalTo($connection1),
                self::identicalTo($connection2),
                self::identicalTo($connection3),
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2,
                (string) $storageId3,
            );

        $tokenStorage->expects(self::exactly(3))
            ->method('getToken')
            ->withParameterSetsInOrder(
                self::identicalTo((string) $storageId1),
                self::identicalTo((string) $storageId2),
                self::identicalTo((string) $storageId3),
            )
            ->willReturnOnConsecutiveCalls(
                $authenticatedToken1,
                $authenticatedToken2,
                $guestToken,
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);
        $topic->add($connection3);

        self::assertEquals([
            new TokenConnection($authenticatedToken1, $connection1),
        ], $repository->findAllWithRoles($topic, ['ROLE_STAFF']));
    }

    public function testReportsWhetherAUserWithTheGivenUsernameHasAConnection(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $repository = $this->createRepository(tokenStorage: $tokenStorage);

        /** @var Stub&WAMPConnection $connection1 */
        $connection1 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection2 */
        $connection2 = self::createStub(WAMPConnection::class);

        /** @var Stub&WAMPConnection $connection3 */
        $connection3 = self::createStub(WAMPConnection::class);

        $storageId1 = 42;
        $storageId2 = 43;

        $username1 = 'user';
        $username2 = 'guest';

        /** @var MockObject&TokenInterface $token1 */
        $token1 = $this->createMock(TokenInterface::class);
        $token1->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn($username1);

        /** @var MockObject&TokenInterface $token2 */
        $token2 = $this->createMock(TokenInterface::class);
        $token2->expects(self::once())
            ->method('getUserIdentifier')
            ->willReturn($username2);

        /** @var MockObject&TokenInterface $token3 */
        $token3 = $this->createMock(TokenInterface::class);
        $token3->expects(self::never())
            ->method('getUserIdentifier');

        $tokenStorage->expects(self::exactly(2))
            ->method('generateStorageId')
            ->withParameterSetsInOrder(
                self::identicalTo($connection1),
                self::identicalTo($connection2),
            )
            ->willReturnOnConsecutiveCalls(
                (string) $storageId1,
                (string) $storageId2,
            );

        $tokenStorage->expects(self::exactly(2))
            ->method('getToken')
            ->withParameterSetsInOrder(
                self::identicalTo((string) $storageId1),
                self::identicalTo((string) $storageId2),
            )
            ->willReturnOnConsecutiveCalls(
                $token1,
                $token2,
            );

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);
        $topic->add($connection3);

        self::assertTrue($repository->hasConnectionForUsername($topic, $username2));
    }

    public function testAConnectionWhichCannotBeAuthenticatedIsTreatedAsAnonymousWithoutStoppingTheLookup(): void
    {
        $logger = new TestLogger();

        $tokenStorage = new DriverBackedTokenStorage(new InMemoryStorageDriver());

        $authenticator = self::createStub(Authenticator::class);
        $authenticator->method('authenticate')
            ->willThrowException(new AuthenticationException('Could not authenticate user.'));

        $repository = new StorageBackedConnectionRepository($tokenStorage, $authenticator, $logger);

        $failingConnection = $this->createConnection('1');
        $authenticatedConnection = $this->createConnection('2');

        $token = new UsernamePasswordToken(new InMemoryUser('user', 'password'), 'main');

        $tokenStorage->addToken($tokenStorage->generateStorageId($authenticatedConnection), $token);

        // The failing connection is first, so the remaining connections are only found if the lookup continues
        $topic = new Topic('testing/123');
        $topic->add($failingConnection);
        $topic->add($authenticatedConnection);

        self::assertEquals(
            [new TokenConnection(new NullToken(), $failingConnection), new TokenConnection($token, $authenticatedConnection)],
            $repository->findAll($topic, true),
        );
        self::assertEquals([new TokenConnection($token, $authenticatedConnection)], $repository->findAll($topic));
        self::assertTrue($repository->hasConnectionForUsername($topic, 'user'));
        self::assertTrue($logger->hasWarningThatContains('Could not find the token for a connection'));
    }

    public function testAnUnexpectedErrorWhileFindingATokenIsNotCaught(): void
    {
        $authenticator = self::createStub(Authenticator::class);
        $authenticator->method('authenticate')
            ->willThrowException(new \LogicException('Something unexpected'));

        $repository = new StorageBackedConnectionRepository(new DriverBackedTokenStorage(new InMemoryStorageDriver()), $authenticator);

        $topic = new Topic('testing/123');
        $topic->add($this->createConnection('1'));

        $this->expectException(\LogicException::class);

        $repository->findAll($topic);
    }

    private function createConnection(string $resourceId): WAMPConnection
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::RESOURCE_ID, $resourceId);

        $connection = self::createStub(WAMPConnection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        return $connection;
    }

    private function createRepository(?TokenStorage $tokenStorage = null, ?Authenticator $authenticator = null): StorageBackedConnectionRepository
    {
        return new StorageBackedConnectionRepository($tokenStorage ?? self::createStub(TokenStorage::class), $authenticator ?? self::createStub(Authenticator::class));
    }
}
