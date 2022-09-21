<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication\Provider;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SessionAuthenticationProviderTest extends TestCase
{
    private const FIREWALLS = ['main'];

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
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects(self::once())
            ->method('has')
            ->with('session')
            ->willReturn(true);

        $attributeStore->expects(self::once())
            ->method('get')
            ->with('session')
            ->willReturn($this->createMock(SessionInterface::class));

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        self::assertTrue($this->provider->supports($connection));
    }

    public function testTheProviderDoesNotSupportAConnectionWhenItDoesNotHaveASession(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects(self::once())
            ->method('has')
            ->with('session')
            ->willReturn(false);

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

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->method('get')
            ->withConsecutive(
                ['session'],
                ['resource_id'],
            )
            ->willReturnOnConsecutiveCalls(
                $session,
                'resource',
                'test',
            );

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
            new InMemoryUser('user', 'password'),
            'main',
            ['ROLE_USER'],
        );

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('get')
            ->with('_security_main')
            ->willReturn(serialize($token));

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->method('get')
            ->withConsecutive(
                ['session'],
                ['resource_id'],
            )
            ->willReturnOnConsecutiveCalls(
                $session,
                'resource',
            );

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

        self::assertEquals($token, $this->provider->authenticate($connection));
    }
}
