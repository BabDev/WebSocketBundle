<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Authentication;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocketBundle\Authentication\DefaultAuthenticator;
use BabDev\WebSocketBundle\Authentication\Provider\AuthenticationProvider;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class DefaultAuthenticatorTest extends TestCase
{
    public function testTheAuthenticatorDoesNotAuthenticateAConnectionWhenItHasNoProviders(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);
        $tokenStorage->expects(self::never())
            ->method('generateStorageId');

        $tokenStorage->expects(self::never())
            ->method('addToken');

        (new DefaultAuthenticator([], $tokenStorage))->authenticate($connection);
    }

    public function testTheAuthenticatorAuthenticatesAConnectionWhenItHasOneProvider(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);

        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);
        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn('conn-123');

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with('conn-123', $token);

        /** @var MockObject&AuthenticationProvider $authenticationProvider */
        $authenticationProvider = $this->createMock(AuthenticationProvider::class);
        $authenticationProvider->expects(self::once())
            ->method('supports')
            ->with($connection)
            ->willReturn(true);

        $authenticationProvider->expects(self::once())
            ->method('authenticate')
            ->with($connection)
            ->willReturn($token);

        (new DefaultAuthenticator([$authenticationProvider], $tokenStorage))->authenticate($connection);
    }

    public function testTheAuthenticatorAuthenticatesAConnectionUsingTheFirstSupportedProvider(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        /** @var MockObject&TokenInterface $token */
        $token = $this->createMock(TokenInterface::class);

        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);
        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn('conn-123');

        $tokenStorage->expects(self::once())
            ->method('addToken')
            ->with('conn-123', $token);

        /** @var MockObject&AuthenticationProvider $authenticationProvider1 */
        $authenticationProvider1 = $this->createMock(AuthenticationProvider::class);
        $authenticationProvider1->expects(self::once())
            ->method('supports')
            ->with($connection)
            ->willReturn(false);

        $authenticationProvider1->expects(self::never())
            ->method('authenticate');

        /** @var MockObject&AuthenticationProvider $authenticationProvider2 */
        $authenticationProvider2 = $this->createMock(AuthenticationProvider::class);
        $authenticationProvider2->expects(self::once())
            ->method('supports')
            ->with($connection)
            ->willReturn(true);

        $authenticationProvider2->expects(self::once())
            ->method('authenticate')
            ->with($connection)
            ->willReturn($token);

        /** @var MockObject&AuthenticationProvider $authenticationProvider3 */
        $authenticationProvider3 = $this->createMock(AuthenticationProvider::class);
        $authenticationProvider3->expects(self::never())
            ->method('supports');

        $authenticationProvider3->expects(self::never())
            ->method('authenticate');

        (new DefaultAuthenticator([$authenticationProvider1, $authenticationProvider2, $authenticationProvider3], $tokenStorage))->authenticate($connection);
    }
}
