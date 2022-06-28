<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BabDev\WebSocket\Server\Http\GuzzleRequestParser;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;
use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use BabDev\WebSocket\Server\Session\SessionFactory;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorageFactory;
use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\MessageHandler\DefaultMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocketBundle\Command\RunWebSocketServerCommand;
use BabDev\WebSocketBundle\Server\ServiceBasedMiddlewareStackBuilder;
use BabDev\WebSocketBundle\Server\DefaultServerFactory;
use BabDev\WebSocketBundle\Server\MiddlewareStackBuilder;
use BabDev\WebSocketBundle\Server\ServerFactory;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('babdev_websocket_server.command.run_websocket_server', RunWebSocketServerCommand::class)
        ->args(
            [
                service(ServerFactory::class),
                service('babdev_websocket_server.event_loop'),
                abstract_arg('server URI'),
            ]
        )
        ->tag('console.command')
    ;

    $services->set('babdev_websocket_server.event_loop', LoopInterface::class)
        ->factory([Loop::class, 'get'])
    ;
    $services->alias(LoopInterface::class, 'babdev_websocket_server.event_loop');

    $services->set('babdev_websocket_server.rfc6455.server_negotiator', ServerNegotiator::class)
        ->args([
            inline_service(RequestVerifier::class),
        ])
    ;

    $services->set('babdev_websocket_server.server.service_based_middleware_stack_builder', ServiceBasedMiddlewareStackBuilder::class)
        ->args([
            service(ServerMiddleware::class)->nullOnInvalid(),
        ])
    ;
    $services->alias(MiddlewareStackBuilder::class, 'babdev_websocket_server.server.service_based_middleware_stack_builder');

    $services->set('babdev_websocket_server.factory.default', DefaultServerFactory::class)
        ->args(
            [
                service(MiddlewareStackBuilder::class),
                service('babdev_websocket_server.event_loop'),
                abstract_arg('server context'),
            ]
        )
    ;
    $services->alias(ServerFactory::class, 'babdev_websocket_server.factory.default');

    // TODO - Set the real services
    $services->set('babdev_websocket_server.server.server_middleware.dispatch_to_message_handler', DispatchMessageToHandler::class)
        ->args([
            inline_service(UrlMatcher::class)->args([
                inline_service(RouteCollection::class),
                inline_service(RequestContext::class),
            ]),
            inline_service(DefaultMessageHandlerResolver::class),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => 0])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.update_topic_subscriptions', UpdateTopicSubscriptions::class)
        ->args([
            abstract_arg('decorated middleware'),
            service(TopicRegistry::class),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -10])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.parse_wamp_message', ParseWAMPMessage::class)
        ->args([
            abstract_arg('decorated middleware'),
            service(TopicRegistry::class),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -20])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.initialize_session', InitializeSession::class)
        ->args([
            abstract_arg('decorated middleware'),
            abstract_arg('session factory'),
            service(OptionsHandler::class),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -30])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.establish_websocket_connection', EstablishWebSocketConnection::class)
        ->args([
            abstract_arg('decorated middleware'),
            service('babdev_websocket_server.rfc6455.server_negotiator'),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -40])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins', RestrictToAllowedOrigins::class)
        ->args([
            abstract_arg('decorated middleware'),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -50])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.parse_http_request', ParseHttpRequest::class)
        ->args([
            abstract_arg('decorated middleware'),
            service(RequestParser::class),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -60])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address', RejectBlockedIpAddress::class)
        ->args([
            abstract_arg('decorated middleware'),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -70])
    ;

    $services->set('babdev_websocket_server.server.options_handler', IniOptionsHandler::class);
    $services->alias(OptionsHandler::class, 'babdev_websocket_server.server.options_handler');

    $services->set('babdev_websocket_server.server.request_parser', GuzzleRequestParser::class);
    $services->alias(RequestParser::class, 'babdev_websocket_server.server.request_parser');

    $services->set('babdev_websocket_server.server.topic_registry', ArrayTopicRegistry::class);
    $services->alias(TopicRegistry::class, 'babdev_websocket_server.server.topic_registry');

    $services->set('babdev_websocket_server.server.session.factory', SessionFactory::class)
        ->args([
            abstract_arg('session storage factory'),
        ])
    ;

    $services->set('babdev_websocket_server.server.session.storage.factory.read_only_native', ReadOnlyNativeSessionStorageFactory::class)
        ->args([
            service(OptionsHandler::class),
            null, // session reader, initialized by the session storage from the PHP options at runtime
            param('session.storage.options'),
            abstract_arg('session handler'),
            null, // metadata bag
        ])
    ;
};
