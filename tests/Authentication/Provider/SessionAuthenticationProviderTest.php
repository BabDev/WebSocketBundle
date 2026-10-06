<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
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

    public function testTheProviderSupportsAConnectionWhenItHasASession(): void
    {
        $provider = $this->createProvider();

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, self::createStub(SessionInterface::class));

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertTrue($provider->supports($connection));
    }

    public function testTheProviderDoesNotSupportAConnectionWhenItDoesNotHaveASession(): void
    {
        $provider = $this->createProvider();

        $attributeStore = new ArrayAttributeStore();

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertFalse($provider->supports($connection));
    }

    public function testATokenIsCreatedAndAddedToStorageWhenAGuestUserWithoutASessionConnects(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $provider = $this->createProvider(tokenStorage: $tokenStorage);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(false);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testAnAuthenticatedUserFromASharedSessionIsAuthenticated(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $provider = $this->createProvider(tokenStorage: $tokenStorage);

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
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        $authenticatedToken = $provider->authenticate($connection);

        // After https://github.com/symfony/symfony/pull/59558 (introduced in Symfony 7.3), the roleNames property is lazily initialized so we need to trigger that
        $authenticatedToken->getRoleNames();

        self::assertEquals($token, $authenticatedToken);
    }

    public function testANullTokenUsedWhenANonSecurityTokenIsExtractedFromTheSession(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $provider = $this->createProvider(tokenStorage: $tokenStorage);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(serialize(new \stdClass()));

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenANonStringIsExtractedFromTheSession(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $provider = $this->createProvider(tokenStorage: $tokenStorage);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(new \stdClass());

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenUnserializingTheTokenRaisesAnError(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $provider = $this->createProvider(tokenStorage: $tokenStorage);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn('O:7:"stdClass":0:{}');

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $storageIdentifier = '42';

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn($storageIdentifier);

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with($storageIdentifier, self::isInstanceOf(TokenInterface::class));

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenTheTokenReferencesAnUnknownClass(): void
    {
        $serializedToken = 'O:30:"App\Security\RemovedTokenClass":0:{}';

        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $provider = $this->createProvider(tokenStorage: $tokenStorage);

        $logger = new TestLogger();

        $provider->setLogger($logger);

        $session = self::createStub(SessionInterface::class);
        $session->method('get')
            ->willReturn($serializedToken);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->willReturn('42');

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with('42', self::isInstanceOf(NullToken::class));

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
        self::assertTrue($logger->hasWarningThatContains('Failed to unserialize the security token from the session.'));
    }

    public function testDoesNotAuthenticateWhenATokenWithoutAUserIsUnserialized(): void
    {
        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $provider = $this->createProvider(tokenStorage: $tokenStorage);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(serialize(new NullToken()));

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $tokenStorage->expects(self::never())
            ->method('generateStorageId');

        $tokenStorage->expects(self::never())
            ->method('addToken');

        $this->expectException(AuthenticationException::class);

        $provider->authenticate($connection);
    }

    private function createProvider(?TokenStorage $tokenStorage = null): SessionAuthenticationProvider
    {
        return new SessionAuthenticationProvider($tokenStorage ?? self::createStub(TokenStorage::class), self::FIREWALLS);
    }
}
