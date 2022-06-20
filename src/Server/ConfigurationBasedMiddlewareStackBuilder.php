<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Server;

use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;
use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use BabDev\WebSocket\Server\WAMP\MessageHandler\DefaultMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class ConfigurationBasedMiddlewareStackBuilder implements MiddlewareStackBuilder
{
    /**
     * @param string[] $allowedOrigins
     * @param string[] $blockedAddresses
     */
    public function __construct(
        private readonly TopicRegistry $topicRegistry,
        private readonly OptionsHandler $optionsHandler,
        private readonly NegotiatorInterface $negotiator,
        private readonly RequestParser $requestParser,
        private readonly ?SessionFactoryInterface $sessionFactory = null,
        private readonly array $allowedOrigins = [],
        private readonly array $blockedAddresses = [],
    ) {
    }

    public function build(): ServerMiddleware
    {
        // TODO - Inject router and resolver
        $middleware = new DispatchMessageToHandler(new UrlMatcher(new RouteCollection(), new RequestContext()), new DefaultMessageHandlerResolver());
        $middleware = new UpdateTopicSubscriptions($middleware, $this->topicRegistry);
        $middleware = new ParseWAMPMessage($middleware, $this->topicRegistry);

        if (null !== $this->sessionFactory) {
            $middleware = new InitializeSession($middleware, $this->sessionFactory, $this->optionsHandler);
        }

        $middleware = new EstablishWebSocketConnection($middleware, $this->negotiator);

        if ([] !== $this->allowedOrigins) {
            $middleware = new RestrictToAllowedOrigins($middleware);

            foreach ($this->allowedOrigins as $origin) {
                $middleware->allowOrigin($origin);
            }
        }

        $middleware = new ParseHttpRequest($middleware, $this->requestParser);

        if ([] !== $this->blockedAddresses) {
            $middleware = new RejectBlockedIpAddress($middleware);

            foreach ($this->blockedAddresses as $address) {
                $middleware->blockAddress($address);
            }
        }

        return $middleware;
    }
}
