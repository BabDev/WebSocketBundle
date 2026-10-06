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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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

        $generateSeries = [
            [$connection1, (string) $storageId1],
            [$connection2, (string) $storageId2],
            [$connection3, (string) $storageId3],
        ];

        $tokenStorage->expects(self::exactly(\count($generateSeries)))
            ->method('generateStorageId')
            ->willReturnCallback(function (Connection $connection) use (&$generateSeries): string {
                [$expectedConnection, $storageId] = array_shift($generateSeries);

                $this->assertSame($expectedConnection, $connection);

                return $storageId;
            });

        $tokenSeries = [
            [(string) $storageId1, $token1],
            [(string) $storageId2, $token2],
            [(string) $storageId3, $token3],
        ];

        $tokenStorage->expects(self::exactly(\count($tokenSeries)))
            ->method('getToken')
            ->willReturnCallback(function (string $id) use (&$tokenSeries): TokenInterface {
                [$expectedId, $token] = array_shift($tokenSeries);

                $this->assertSame($expectedId, $id);

                return $token;
            });

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

        $generateSeries = [
            [$connection1, (string) $storageId1],
            [$connection2, (string) $storageId2],
        ];

        $tokenStorage->expects(self::exactly(\count($generateSeries)))
            ->method('generateStorageId')
            ->willReturnCallback(function (Connection $connection) use (&$generateSeries): string {
                [$expectedConnection, $storageId] = array_shift($generateSeries);

                $this->assertSame($expectedConnection, $connection);

                return $storageId;
            });

        $tokenSeries = [
            [(string) $storageId1, $authenticatedToken],
            [(string) $storageId2, $guestToken],
        ];

        $tokenStorage->expects(self::exactly(\count($tokenSeries)))
            ->method('getToken')
            ->willReturnCallback(function (string $id) use (&$tokenSeries): TokenInterface {
                [$expectedId, $token] = array_shift($tokenSeries);

                $this->assertSame($expectedId, $id);

                return $token;
            });

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

        $generateSeries = [
            [$connection1, (string) $storageId1],
            [$connection2, (string) $storageId2],
        ];

        $tokenStorage->expects(self::exactly(\count($generateSeries)))
            ->method('generateStorageId')
            ->willReturnCallback(function (Connection $connection) use (&$generateSeries): string {
                [$expectedConnection, $storageId] = array_shift($generateSeries);

                $this->assertSame($expectedConnection, $connection);

                return $storageId;
            });

        $tokenSeries = [
            [(string) $storageId1, $authenticatedToken],
            [(string) $storageId2, $guestToken],
        ];

        $tokenStorage->expects(self::exactly(\count($tokenSeries)))
            ->method('getToken')
            ->willReturnCallback(function (string $id) use (&$tokenSeries): TokenInterface {
                [$expectedId, $token] = array_shift($tokenSeries);

                $this->assertSame($expectedId, $id);

                return $token;
            });

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

        $generateSeries = [
            [$connection1, (string) $storageId1],
            [$connection2, (string) $storageId2],
            [$connection3, (string) $storageId3],
        ];

        $tokenStorage->expects(self::exactly(\count($generateSeries)))
            ->method('generateStorageId')
            ->willReturnCallback(function (Connection $connection) use (&$generateSeries): string {
                [$expectedConnection, $storageId] = array_shift($generateSeries);

                $this->assertSame($expectedConnection, $connection);

                return $storageId;
            });

        $tokenSeries = [
            [(string) $storageId1, $authenticatedToken1],
            [(string) $storageId2, $authenticatedToken2],
            [(string) $storageId3, $guestToken],
        ];

        $tokenStorage->expects(self::exactly(\count($tokenSeries)))
            ->method('getToken')
            ->willReturnCallback(function (string $id) use (&$tokenSeries): TokenInterface {
                [$expectedId, $token] = array_shift($tokenSeries);

                $this->assertSame($expectedId, $id);

                return $token;
            });

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

        $generateSeries = [
            [$connection1, (string) $storageId1],
            [$connection2, (string) $storageId2],
        ];

        $tokenStorage->expects(self::exactly(\count($generateSeries)))
            ->method('generateStorageId')
            ->willReturnCallback(function (Connection $connection) use (&$generateSeries): string {
                [$expectedConnection, $storageId] = array_shift($generateSeries);

                $this->assertSame($expectedConnection, $connection);

                return $storageId;
            });

        $tokenSeries = [
            [(string) $storageId1, $token1],
            [(string) $storageId2, $token2],
        ];

        $tokenStorage->expects(self::exactly(\count($tokenSeries)))
            ->method('getToken')
            ->willReturnCallback(function (string $id) use (&$tokenSeries): TokenInterface {
                [$expectedId, $token] = array_shift($tokenSeries);

                $this->assertSame($expectedId, $id);

                return $token;
            });

        $topic = new Topic('testing/123');
        $topic->add($connection1);
        $topic->add($connection2);
        $topic->add($connection3);

        self::assertTrue($repository->hasConnectionForUsername($topic, $username2));
    }

    private function createRepository(?TokenStorage $tokenStorage = null, ?Authenticator $authenticator = null): StorageBackedConnectionRepository
    {
        return new StorageBackedConnectionRepository($tokenStorage ?? self::createStub(TokenStorage::class), $authenticator ?? self::createStub(Authenticator::class));
    }
}
