<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Authentication\Authenticator;
use BabDev\WebSocketBundle\Authentication\Exception\AuthenticationException;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Server\Middleware\AuthenticateUser;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class AuthenticateUserTest extends TestCase
{
    #[TestDox('Handles a new connection being opened')]
    public function testOnOpen(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&Authenticator $authenticator */
        $authenticator = $this->createMock(Authenticator::class);

        $middleware = $this->createMiddleware(decoratedMiddleware: $decoratedMiddleware, authenticator: $authenticator);

        /** @var Stub&Connection $connection */
        $connection = self::createStub(Connection::class);

        $authenticator->expects(self::once())
            ->method('authenticate')
            ->with($connection);

        $decoratedMiddleware->expects(self::once())
            ->method('onOpen')
            ->with($connection);

        $middleware->onOpen($connection);
    }

    #[TestDox('Rejects a connection which cannot be authenticated')]
    public function testOnOpenWhenAuthenticationFails(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $exception = new AuthenticationException('Could not authenticate user.');

        $authenticator = self::createStub(Authenticator::class);
        $authenticator->method('authenticate')
            ->willThrowException($exception);

        $middleware = $this->createMiddleware(decoratedMiddleware: $decoratedMiddleware, authenticator: $authenticator);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('send')
            ->with(self::stringStartsWith('HTTP/1.1 401 Unauthorized'));

        $connection->expects(self::once())
            ->method('close');

        $decoratedMiddleware->expects(self::never())
            ->method('onOpen');

        $this->expectExceptionObject($exception);

        $middleware->onOpen($connection);
    }

    #[TestDox('Handles incoming data on the connection')]
    public function testOnMessage(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware(decoratedMiddleware: $decoratedMiddleware);

        $data = 'Testing';

        /** @var Stub&Connection $connection */
        $connection = self::createStub(Connection::class);

        $decoratedMiddleware->expects(self::once())
            ->method('onMessage')
            ->with($connection, $data);

        $middleware->onMessage($connection, $data);
    }

    #[TestDox('Closes the connection')]
    public function testOnClose(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $middleware = $this->createMiddleware(decoratedMiddleware: $decoratedMiddleware, tokenStorage: $tokenStorage);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set(AttributeKey::RESOURCE_ID, 'resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedMiddleware->expects(self::once())
            ->method('onClose')
            ->with($connection);

        $tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn('resource');

        $tokenStorage->expects(self::once())
            ->method('hasToken')
            ->with('resource')
            ->willReturn(true);

        $tokenStorage->expects(self::once())
            ->method('getToken')
            ->with('resource')
            ->willReturn(self::createStub(TokenInterface::class));

        $tokenStorage->expects(self::once())
            ->method('removeToken')
            ->with('resource')
            ->willReturn(true);

        $middleware->onClose($connection);
    }

    #[TestDox('Removes the token when the decorated middleware fails while closing a connection')]
    public function testOnCloseRemovesTheTokenWhenTheDecoratedMiddlewareFails(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&TokenStorage $tokenStorage */
        $tokenStorage = $this->createMock(TokenStorage::class);

        $middleware = $this->createMiddleware(decoratedMiddleware: $decoratedMiddleware, tokenStorage: $tokenStorage);

        $attributeStore = self::createStub(AttributeStore::class);
        $attributeStore->method('get')
            ->willReturn('resource');

        $connection = self::createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $exception = new \RuntimeException('Failed to close the connection');

        $decoratedMiddleware->expects(self::once())
            ->method('onClose')
            ->with($connection)
            ->willThrowException($exception);

        $tokenStorage->method('generateStorageId')
            ->willReturn('resource');

        $tokenStorage->method('hasToken')
            ->willReturn(true);

        $tokenStorage->method('getToken')
            ->willReturn(self::createStub(TokenInterface::class));

        $tokenStorage->expects(self::once())
            ->method('removeToken')
            ->with('resource')
            ->willReturn(true);

        $this->expectExceptionObject($exception);

        $middleware->onClose($connection);
    }

    #[TestDox('Handles an error')]
    public function testOnError(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware(decoratedMiddleware: $decoratedMiddleware);

        /** @var Stub&Connection $connection */
        $connection = self::createStub(Connection::class);

        $error = new \Exception('Testing');

        $decoratedMiddleware->expects(self::once())
            ->method('onError')
            ->with($connection, $error);

        $middleware->onError($connection, $error);
    }

    private function createMiddleware(?ServerMiddleware $decoratedMiddleware = null, ?Authenticator $authenticator = null, ?TokenStorage $tokenStorage = null): AuthenticateUser
    {
        return new AuthenticateUser($decoratedMiddleware ?? self::createStub(ServerMiddleware::class), $authenticator ?? self::createStub(Authenticator::class), $tokenStorage ?? self::createStub(TokenStorage::class));
    }
}
