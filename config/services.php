<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BabDev\WebSocket\Server\Http\GuzzleRequestParser;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocketBundle\Command\RunWebSocketServerCommand;
use BabDev\WebSocketBundle\Server\ConfigurationBasedMiddlewareStackBuilder;
use BabDev\WebSocketBundle\Server\DefaultServerFactory;
use BabDev\WebSocketBundle\Server\MiddlewareStackBuilder;
use BabDev\WebSocketBundle\Server\ServerFactory;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

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

    $services->set('babdev_websocket_server.server.configuration_based_middleware_stack_builder', ConfigurationBasedMiddlewareStackBuilder::class)
        ->args([
            service(TopicRegistry::class),
            service(OptionsHandler::class),
            service('babdev_websocket_server.rfc6455.server_negotiator'),
            service(RequestParser::class),
            null, // session factory, optional integration
            abstract_arg('allowed origins'),
            abstract_arg('blocked IP addresses'),
        ])
    ;
    $services->alias(MiddlewareStackBuilder::class, 'babdev_websocket_server.server.configuration_based_middleware_stack_builder');

    $services->set('babdev_websocket_server.factory.default', DefaultServerFactory::class)
        ->args(
            [
                service('babdev_websocket_server.server.middleware_stack_builder'),
                service('babdev_websocket_server.event_loop'),
                abstract_arg('server context'),
            ]
        )
    ;
    $services->alias(ServerFactory::class, 'babdev_websocket_server.factory.default');

    $services->set('babdev_websocket_server.server.options_handler', IniOptionsHandler::class);
    $services->alias(OptionsHandler::class, 'babdev_websocket_server.server.options_handler');

    $services->set('babdev_websocket_server.server.request_parser', GuzzleRequestParser::class);
    $services->alias(RequestParser::class, 'babdev_websocket_server.server.request_parser');

    $services->set('babdev_websocket_server.server.topic_registry', ArrayTopicRegistry::class);
    $services->alias(TopicRegistry::class, 'babdev_websocket_server.server.topic_registry');
};
