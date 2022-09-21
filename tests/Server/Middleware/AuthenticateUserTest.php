<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Authentication\Authenticator;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Server\Middleware\AuthenticateUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

final class AuthenticateUserTest extends TestCase
{
    private MockObject&ServerMiddleware $decoratedMiddleware;

    private MockObject&Authenticator $authenticator;

    private MockObject&TokenStorage $tokenStorage;

    private AuthenticateUser $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $this->authenticator = $this->createMock(Authenticator::class);
        $this->tokenStorage = $this->createMock(TokenStorage::class);

        $this->middleware = new AuthenticateUser($this->decoratedMiddleware, $this->authenticator, $this->tokenStorage);
    }

    /**
     * @testdox Handles a new connection being opened
     */
    public function testOnOpen(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->authenticator->expects(self::once())
            ->method('authenticate')
            ->with($connection);

        $this->decoratedMiddleware->expects(self::once())
            ->method('onOpen')
            ->with($connection);

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles incoming data on the connection
     */
    public function testOnMessage(): void
    {
        $data = 'Testing';

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects(self::once())
            ->method('onMessage')
            ->with($connection, $data);

        $this->middleware->onMessage($connection, $data);
    }

    /**
     * @testdox Closes the connection
     */
    public function testOnClose(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->method('get')
            ->with('resource_id')
            ->willReturn('resource');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects(self::once())
            ->method('onClose')
            ->with($connection);

        /** @var MockObject&AbstractToken $token */
        $token = $this->createMock(AbstractToken::class);
        $token->expects(self::once())
            ->method(method_exists(AbstractToken::class, 'getUserIdentifier') ? 'getUserIdentifier' : 'getUsername')
            ->willReturn('username');

        $this->tokenStorage->expects(self::once())
            ->method('generateStorageId')
            ->with($connection)
            ->willReturn('resource');

        $this->tokenStorage->expects(self::once())
            ->method('hasToken')
            ->with('resource')
            ->willReturn(true);

        $this->tokenStorage->expects(self::once())
            ->method('getToken')
            ->with('resource')
            ->willReturn($token);

        $this->tokenStorage->expects(self::once())
            ->method('removeToken')
            ->with('resource')
            ->willReturn(true);

        $this->middleware->onClose($connection);
    }

    /**
     * @testdox Handles an error
     */
    public function testOnError(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $error = new \Exception('Testing');

        $this->decoratedMiddleware->expects(self::once())
            ->method('onError')
            ->with($connection, $error);

        $this->middleware->onError($connection, $error);
    }
}
