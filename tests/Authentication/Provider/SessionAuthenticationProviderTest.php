<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserProviderInterface;

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
            ->willReturnStrictMap([
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

    public function testTheUserIsReloadedFromTheUserProvider(): void
    {
        $userProvider = new InMemoryUserProvider(['user' => ['password' => 'password', 'roles' => ['ROLE_USER']]]);

        $token = $this->authenticateWithSessionToken(
            new UsernamePasswordToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']),
            [$userProvider],
        );

        self::assertInstanceOf(UsernamePasswordToken::class, $token);
        self::assertEquals($userProvider->loadUserByIdentifier('user'), $token->getUser());
    }

    /**
     * @return iterable<string, array{array<string, array{password: string, roles: list<string>}>}>
     */
    public static function deauthenticatedUsers(): iterable
    {
        yield 'roles changed' => [['user' => ['password' => 'password', 'roles' => ['ROLE_ADMIN']]]];
        yield 'password changed' => [['user' => ['password' => 'changed', 'roles' => ['ROLE_USER']]]];
        yield 'user deleted' => [[]];
    }

    /**
     * @param array<string, array{password: string, roles: list<string>}> $users
     */
    #[DataProvider('deauthenticatedUsers')]
    public function testTheUserIsDeauthenticatedWhenTheUserHasChanged(array $users): void
    {
        $token = $this->authenticateWithSessionToken(
            new UsernamePasswordToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']),
            [new InMemoryUserProvider($users)],
        );

        self::assertInstanceOf(NullToken::class, $token);
    }

    public function testTheNextUserProviderIsUsedWhenAProviderDoesNotSupportTheUser(): void
    {
        $unsupportingProvider = self::createStub(UserProviderInterface::class);
        $unsupportingProvider->method('supportsClass')
            ->willReturn(true);
        $unsupportingProvider->method('refreshUser')
            ->willThrowException(new UnsupportedUserException());

        $token = $this->authenticateWithSessionToken(
            new UsernamePasswordToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']),
            [$unsupportingProvider, new InMemoryUserProvider(['user' => ['password' => 'password', 'roles' => ['ROLE_USER']]])],
        );

        self::assertSame('user', $token->getUserIdentifier());
    }

    public function testAnErrorIsRaisedWhenNoUserProviderSupportsTheUser(): void
    {
        $unsupportingProvider = self::createStub(UserProviderInterface::class);
        $unsupportingProvider->method('supportsClass')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIs(\sprintf('There is no user provider for user "%s". Shouldn\'t the "supportsClass()" method of your user provider return true for this classname?', InMemoryUser::class));

        $this->authenticateWithSessionToken(
            new UsernamePasswordToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']),
            [$unsupportingProvider],
        );
    }

    /**
     * @return iterable<string, array{iterable<mixed>}>
     */
    public static function withoutUserProviders(): iterable
    {
        yield 'no user providers' => [[]];
        yield 'empty lazy user providers' => [new RewindableGenerator(static fn (): \Generator => yield from [], 0)];
    }

    /**
     * @param iterable<mixed> $userProviders
     */
    #[DataProvider('withoutUserProviders')]
    public function testTheTokenIsUsedAsIsWithoutUserProviders(iterable $userProviders): void
    {
        $token = $this->authenticateWithSessionToken(
            new UsernamePasswordToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'main', ['ROLE_USER']),
            $userProviders,
        );

        self::assertSame('user', $token->getUserIdentifier());
    }

    public function testAnImpersonatedUserIsDeauthenticatedWhenTheImpersonatorNoLongerExists(): void
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('admin', 'password', ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);

        $token = $this->authenticateWithSessionToken(
            new SwitchUserToken(new InMemoryUser('user', 'password', ['ROLE_USER']), 'main', ['ROLE_USER'], $originalToken),
            [new InMemoryUserProvider(['user' => ['password' => 'password', 'roles' => ['ROLE_USER']]])],
        );

        self::assertInstanceOf(NullToken::class, $token);
    }

    /**
     * @param list<string> $firewalls
     */
    private function createProvider(array $firewalls = self::FIREWALLS): SessionAuthenticationProvider
    {
        return new SessionAuthenticationProvider($firewalls);
    }

    /**
     * @param iterable<mixed> $userProviders
     */
    private function authenticateWithSessionToken(TokenInterface $token, iterable $userProviders): TokenInterface
    {
        $session = self::createStub(SessionInterface::class);
        $session->method('get')
            ->willReturn(serialize($token));

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::SESSION, $session);
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        return new SessionAuthenticationProvider(self::FIREWALLS, new IniOptionsHandler(), $userProviders)->authenticate($connection);
    }
}
