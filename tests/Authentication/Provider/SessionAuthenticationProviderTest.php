<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SessionAuthenticationProviderTest extends TestCase
{
    private const array FIREWALLS = ['main'];

    private readonly MockObject&TokenStorage $tokenStorage;

    private readonly SessionAuthenticationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenStorage = $this->createMock(TokenStorage::class);

        $this->provider = new SessionAuthenticationProvider($this->tokenStorage, self::FIREWALLS);
    }

    public function testTheProviderSupportsAConnectionWhenItHasASession(): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', self::createStub(SessionInterface::class));

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertTrue($this->provider->supports($connection));
    }

    public function testTheProviderDoesNotSupportAConnectionWhenItDoesNotHaveASession(): void
    {
        $attributeStore = new ArrayAttributeStore();

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertFalse($this->provider->supports($connection));
    }

    public function testATokenIsCreatedAndAddedToStorageWhenAGuestUserWithoutASessionConnects(): void
    {
        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(false);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', $session);
        $attributeStore->set('resource_id', 'resource');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $this->tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $this->provider->authenticate($connection));
    }

    public function testAnAuthenticatedUserFromASharedSessionIsAuthenticated(): void
    {
        $token = new UsernamePasswordToken(
            new InMemoryUser('user', 'password', ['ROLE_USER']),
            'main',
            ['ROLE_USER'],
        );

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(serialize($token));

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', $session);
        $attributeStore->set('resource_id', 'resource');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $this->tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        $authenticatedToken = $this->provider->authenticate($connection);

        // After https://github.com/symfony/symfony/pull/59558 (introduced in Symfony 7.3), the roleNames property is lazily initialized so we need to trigger that
        $authenticatedToken->getRoleNames();

        self::assertEquals($token, $authenticatedToken);
    }

    public function testANullTokenUsedWhenANonSecurityTokenIsExtractedFromTheSession(): void
    {
        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(serialize(new \stdClass()));

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', $session);
        $attributeStore->set('resource_id', 'resource');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $this->tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $this->provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenANonStringIsExtractedFromTheSession(): void
    {
        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(new \stdClass());

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', $session);
        $attributeStore->set('resource_id', 'resource');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $this->tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $this->provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenUnserializingTheTokenRaisesAnError(): void
    {
        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn('O:7:"stdClass":0:{}');

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', $session);
        $attributeStore->set('resource_id', 'resource');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $this->tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $this->provider->authenticate($connection));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSerializedTokens(): iterable
    {
        yield 'truncated data' => ['O:8:"stdClass":0:'];
        yield 'unknown class' => ['O:30:"App\Security\RemovedTokenClass":0:{}'];
    }

    #[DataProvider('invalidSerializedTokens')]
    public function testANullTokenUsedWhenTheTokenCannotBeUnserialized(string $serializedToken): void
    {
        $logger = new TestLogger();

        $this->provider->setLogger($logger);

        $session = self::createStub(SessionInterface::class);
        $session->method('get')
            ->willReturn($serializedToken);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', $session);
        $attributeStore->set('resource_id', 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn('42');

        $this->tokenStorage->expects(self::once())
            ->method('addToken')
            ->with('42', self::isInstanceOf(NullToken::class));

        self::assertInstanceOf(NullToken::class, $this->provider->authenticate($connection));
        self::assertTrue($logger->hasWarningThatContains('Failed to unserialize the security token from the session.'));
    }

    public function testDoesNotAuthenticateWhenATokenWithoutAUserIsUnserialized(): void
    {
        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(serialize(new NullToken()));

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('session', $session);
        $attributeStore->set('resource_id', 'resource');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->tokenStorage->expects(self::never())
            ->method('generateStorageId');

        $this->tokenStorage->expects(self::never())
            ->method('addToken');

        $this->expectException(AuthenticationException::class);

        $this->provider->authenticate($connection);
    }
}
