<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Server;

use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;
use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocketBundle\Server\ConfigurationBasedMiddlewareStackBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class ConfigurationBasedMiddlewareStackBuilderTest extends TestCase
{
    private readonly MockObject & TopicRegistry $topicRegistry;
    private readonly MockObject & OptionsHandler $optionsHandler;
    private readonly MockObject & NegotiatorInterface $negotiator;
    private readonly MockObject & RequestParser $requestParser;

    protected function setUp(): void
    {
        $this->topicRegistry = $this->createMock(TopicRegistry::class);
        $this->optionsHandler = $this->createMock(OptionsHandler::class);
        $this->negotiator = $this->createMock(NegotiatorInterface::class);
        $this->requestParser = $this->createMock(RequestParser::class);
    }

    public function testCreatesMiddlewareStackWithRequiredComponents(): void
    {
        self::assertInstanceOf(ParseHttpRequest::class, $middleware = $this->createBuilder()->build());
        self::assertInstanceOf(EstablishWebSocketConnection::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(ParseWAMPMessage::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(UpdateTopicSubscriptions::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(DispatchMessageToHandler::class, $this->getPropertyFromClassInstance($middleware, 'middleware'));
    }

    public function testCreatesMiddlewareStackWithAllOptionalComponents(): void
    {
        /** @var MockObject&SessionFactoryInterface $sessionFactory */
        $sessionFactory = $this->createMock(SessionFactoryInterface::class);

        self::assertInstanceOf(RejectBlockedIpAddress::class, $middleware = $this->createBuilder($sessionFactory, ['localhost'], ['0.0.0.0/0'])->build());
        self::assertInstanceOf(ParseHttpRequest::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(RestrictToAllowedOrigins::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(EstablishWebSocketConnection::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(InitializeSession::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(ParseWAMPMessage::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(UpdateTopicSubscriptions::class, $middleware = $this->getPropertyFromClassInstance($middleware, 'middleware'));
        self::assertInstanceOf(DispatchMessageToHandler::class, $this->getPropertyFromClassInstance($middleware, 'middleware'));
    }

    private function createBuilder(
        ?SessionFactoryInterface $sessionFactory = null,
        array $allowedOrigins = [],
        array $blockedAddresses = [],
    ): ConfigurationBasedMiddlewareStackBuilder {
        return new ConfigurationBasedMiddlewareStackBuilder(
            $this->topicRegistry,
            $this->optionsHandler,
            $this->negotiator,
            $this->requestParser,
            $sessionFactory,
            $allowedOrigins,
            $blockedAddresses,
        );
    }

    /**
     * @throws \InvalidArgumentException if the requested property does not exist on the given class instance
     */
    private function getPropertyFromClassInstance(object $classInstance, string $property): mixed
    {
        $refl = new \ReflectionClass($classInstance);

        if (!$refl->hasProperty($property)) {
            throw new \InvalidArgumentException(sprintf('The %s class does not have a property named "%s".', \get_class($classInstance), $property));
        }

        return $refl->getProperty($property)
            ->getValue($classInstance);
    }
}
