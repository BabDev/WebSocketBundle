<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
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

    public function testANullTokenIsReturnedWhenAGuestUserWithoutASessionConnects(): void
    {
        $provider = $this->createProvider();

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

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testAnAuthenticatedUserFromASharedSessionIsAuthenticated(): void
    {
        $provider = $this->createProvider();

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

        $authenticatedToken = $provider->authenticate($connection);

        // After https://github.com/symfony/symfony/pull/59558 (introduced in Symfony 7.3), the roleNames property is lazily initialized so we need to trigger that
        $authenticatedToken->getRoleNames();

        self::assertEquals($token, $authenticatedToken);
    }

    public function testTheNextContextIsCheckedWhenTheSessionHasANonStringValue(): void
    {
        $provider = $this->createProvider(firewalls: ['admin', 'main']);

        $token = new UsernamePasswordToken(
            new InMemoryUser('user', 'password', ['ROLE_USER']),
            'main',
            ['ROLE_USER'],
        );

        $session = self::createStub(SessionInterface::class);
        $session->method('get')
            ->willReturnMap([
                ['_security_admin', false, ['not' => 'a token']],
                ['_security_main', false, serialize($token)],
            ]);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertSame('user', $provider->authenticate($connection)->getUserIdentifier());
    }

    public function testANullTokenUsedWhenANonSecurityTokenIsExtractedFromTheSession(): void
    {
        $provider = $this->createProvider();

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

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenANonStringIsExtractedFromTheSession(): void
    {
        $provider = $this->createProvider();

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

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenUnserializingTheTokenRaisesAnError(): void
    {
        $provider = $this->createProvider();

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

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
    }

    public function testANullTokenUsedWhenTheTokenReferencesAnUnknownClass(): void
    {
        $serializedToken = 'O:30:"App\Security\RemovedTokenClass":0:{}';

        $provider = $this->createProvider();

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

        self::assertInstanceOf(NullToken::class, $provider->authenticate($connection));
        self::assertTrue($logger->hasWarningThatContains('Failed to unserialize the security token from the session.'));
    }

    public function testDoesNotAuthenticateWhenATokenWithoutAUserIsUnserialized(): void
    {
        $provider = $this->createProvider();

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

        $this->expectException(AuthenticationException::class);

        $provider->authenticate($connection);
    }

    /**
     * @param list<string> $firewalls
     */
    private function createProvider(array $firewalls = self::FIREWALLS): SessionAuthenticationProvider
    {
        return new SessionAuthenticationProvider($firewalls);
    }
}
