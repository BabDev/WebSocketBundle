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
use BabDev\WebSocket\Server\WAMP\MessageHandler\MessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\MessageHandler\PsrContainerMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocketBundle\Authentication\Authenticator;
use BabDev\WebSocketBundle\Authentication\ConnectionRepository;
use BabDev\WebSocketBundle\Authentication\DefaultAuthenticator;
use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use BabDev\WebSocketBundle\Authentication\Storage\Driver\InMemoryStorageDriver;
use BabDev\WebSocketBundle\Authentication\Storage\Driver\StorageDriver;
use BabDev\WebSocketBundle\Authentication\Storage\DriverBackedTokenStorage;
use BabDev\WebSocketBundle\Authentication\Storage\TokenStorage;
use BabDev\WebSocketBundle\Authentication\StorageBackedConnectionRepository;
use BabDev\WebSocketBundle\CacheWarmer\RouterCacheWarmer;
use BabDev\WebSocketBundle\Command\RunWebSocketServerCommand;
use BabDev\WebSocketBundle\Event\AfterLoopStopped;
use BabDev\WebSocketBundle\EventListener\ClearTokenStorageListener;
use BabDev\WebSocketBundle\EventListener\PeriodicManagerSubscriber;
use BabDev\WebSocketBundle\PeriodicManager\ArrayPeriodicManagerRegistry;
use BabDev\WebSocketBundle\PeriodicManager\PeriodicManagerRegistry;
use BabDev\WebSocketBundle\PeriodicManager\PingDoctrineDBALConnectionsPeriodicManager;
use BabDev\WebSocketBundle\Routing\Loader\AttributeLoader;
use BabDev\WebSocketBundle\Server\Middleware\AuthenticateUser;
use BabDev\WebSocketBundle\Server\ServiceBasedMiddlewareStackBuilder;
use BabDev\WebSocketBundle\Server\DefaultServerFactory;
use BabDev\WebSocketBundle\Server\DefaultSocketServerFactory;
use BabDev\WebSocketBundle\Server\MiddlewareStackBuilder;
use BabDev\WebSocketBundle\Server\ServerFactory;
use BabDev\WebSocketBundle\Server\SocketServerFactory;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\Loader\ContainerLoader;
use Symfony\Component\Routing\Loader\DirectoryLoader;
use Symfony\Component\Routing\Loader\GlobFileLoader;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\Loader\Psr4DirectoryLoader;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('babdev_websocket_server.authentication.authenticator', DefaultAuthenticator::class)
        ->args([
            abstract_arg('authentication providers'),
            service(TokenStorage::class),
        ])
        ->call('setLogger', [
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'websocket'])
    ;
    $services->alias(Authenticator::class, 'babdev_websocket_server.authentication.authenticator');

    $services->set('babdev_websocket_server.authentication.connection_repository.storage', StorageBackedConnectionRepository::class)
        ->args([
            service(TokenStorage::class),
            service(Authenticator::class),
        ])
    ;
    $services->alias(ConnectionRepository::class, 'babdev_websocket_server.authentication.connection_repository.storage');

    $services->set('babdev_websocket_server.authentication.provider.session', SessionAuthenticationProvider::class)
        ->args([
            service(TokenStorage::class),
            abstract_arg('firewalls'),
        ])
        ->call('setLogger', [
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'websocket'])
    ;

    $services->set('babdev_websocket_server.authentication.storage.driver.in_memory', InMemoryStorageDriver::class);
    $services->alias(StorageDriver::class, 'babdev_websocket_server.authentication.storage.driver.in_memory');

    $services->set('babdev_websocket_server.authentication.token_storage.driver', DriverBackedTokenStorage::class)
        ->args([
            service(StorageDriver::class),
        ])
        ->call('setLogger', [
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'websocket'])
    ;
    $services->alias(TokenStorage::class, 'babdev_websocket_server.authentication.token_storage.driver');

    $services->set('babdev_websocket_server.command.router_debug', RouterDebugCommand::class)
        ->args([
            service('babdev_websocket_server.router'),
            service('debug.file_link_formatter')->nullOnInvalid(),
        ])
        ->tag('console.command', ['command' => 'babdev:websocket-server:debug:router', 'description' => "Display current routes for an application's websocket server."])
    ;

    $services->set('babdev_websocket_server.command.run_websocket_server', RunWebSocketServerCommand::class)
        ->args([
            service('event_dispatcher')->nullOnInvalid(),
            service(SocketServerFactory::class),
            service(ServerFactory::class),
            service(LoopInterface::class),
            abstract_arg('server URI'),
        ])
        ->tag('console.command')
    ;

    $services->set('babdev_websocket_server.event_loop', LoopInterface::class)
        ->factory([Loop::class, 'get'])
    ;
    $services->alias(LoopInterface::class, 'babdev_websocket_server.event_loop');

    $services->set('babdev_websocket_server.event_listener.clear_token_storage', ClearTokenStorageListener::class)
        ->args([
            service(TokenStorage::class),
        ])
        ->tag('kernel.event_listener', ['event' => AfterLoopStopped::class])
    ;

    $services->set('babdev_websocket_server.event_subscriber.periodic_manager', PeriodicManagerSubscriber::class)
        ->args([
            service(PeriodicManagerRegistry::class),
        ])
        ->tag('kernel.event_subscriber')
    ;

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

    $services->set('babdev_websocket_server.message_handler_resolver.psr_container', PsrContainerMessageHandlerResolver::class)
        ->args([
            tagged_locator('babdev_websocket_server.message_handler'),
        ])
    ;
    $services->alias(MessageHandlerResolver::class, 'babdev_websocket_server.message_handler_resolver.psr_container');

    $services->set('babdev_websocket_server.periodic_manager.ping_doctrine_dbal_connections', PingDoctrineDBALConnectionsPeriodicManager::class)
        ->args([
            tagged_iterator('babdev_websocket_server.ping.dbal.connection'),
            abstract_arg('ping interval'),
        ])
        ->tag('babdev_websocket_server.periodic_manager')
    ;

    $services->set('babdev_websocket_server.periodic_manager.registry', ArrayPeriodicManagerRegistry::class)
        ->args([
            tagged_iterator('babdev_websocket_server.periodic_manager'),
        ])
    ;
    $services->alias(PeriodicManagerRegistry::class, 'babdev_websocket_server.periodic_manager.registry');

    $services->set('babdev_websocket_server.router', Router::class)
        ->args([
            service(PsrContainerInterface::class),
            abstract_arg('routing resource'),
            [
                'cache_dir' => param('kernel.cache_dir') . '/websocket-router',
                'debug' => param('kernel.debug'),
                'generator_class' => CompiledUrlGenerator::class,
                'generator_dumper_class' => CompiledUrlGeneratorDumper::class,
                'matcher_class' => CompiledUrlMatcher::class,
                'matcher_dumper_class' => CompiledUrlMatcherDumper::class,
            ],
            service('babdev_websocket_server.router.request_context')->ignoreOnInvalid(),
            service('parameter_bag')->ignoreOnInvalid(),
            service('logger')->ignoreOnInvalid(),
            param('kernel.default_locale'),
        ])
        ->call('setConfigCacheFactory', [
            service('config_cache_factory'),
        ])
        ->tag('monolog.logger', ['channel' => 'websocket_router'])
        ->tag('container.service_subscriber', ['key' => 'routing.loader', 'id' => 'babdev_websocket_server.routing.loader'])
    ;

    $services->set('babdev_websocket_server.router.cache_warmer', RouterCacheWarmer::class)
        ->args([
            service(PsrContainerInterface::class),
            param('kernel.cache_dir') . '/websocket-router',
        ])
        ->tag('container.service_subscriber', ['id' => 'babdev_websocket_server.router'])
        ->tag('kernel.cache_warmer')
    ;

    $services->set('babdev_websocket_server.router.request_context', RequestContext::class)
        ->call('setParameter', [
            '_functions',
            service('router.expression_language_provider')->ignoreOnInvalid(),
        ])
    ;

    $services->set('babdev_websocket_server.routing.loader', DelegatingLoader::class)
        ->public()
        ->args([
            service('babdev_websocket_server.routing.resolver'),
        ])
    ;

    $services->set('babdev_websocket_server.routing.loader.attribute', AttributeLoader::class)
        ->args([
            param('kernel.environment'),
        ])
        ->tag('babdev_websocket_server.routing.loader', ['priority' => -10])
    ;

    $services->set('babdev_websocket_server.routing.loader.attribute.directory', AttributeDirectoryLoader::class)
        ->args([
            service('file_locator'),
            service('babdev_websocket_server.routing.loader.attribute'),
        ])
        ->tag('babdev_websocket_server.routing.loader', ['priority' => -10])
    ;

    $services->set('babdev_websocket_server.routing.loader.attribute.file', AttributeFileLoader::class)
        ->args([
            service('file_locator'),
            service('babdev_websocket_server.routing.loader.attribute'),
        ])
        ->tag('babdev_websocket_server.routing.loader', ['priority' => -10])
    ;

    $services->set('babdev_websocket_server.routing.loader.container', ContainerLoader::class)
        ->args([
            tagged_locator('babdev_websocket_server.routing.route_loader'),
            param('kernel.environment'),
        ])
        ->tag('babdev_websocket_server.routing.loader')
    ;

    $services->set('babdev_websocket_server.routing.loader.directory', DirectoryLoader::class)
        ->args([
            service('file_locator'),
            param('kernel.environment'),
        ])
        ->tag('babdev_websocket_server.routing.loader')
    ;

    $services->set('babdev_websocket_server.routing.loader.glob', GlobFileLoader::class)
        ->args([
            service('file_locator'),
            param('kernel.environment'),
        ])
        ->tag('babdev_websocket_server.routing.loader')
    ;

    $services->set('babdev_websocket_server.routing.loader.php', PhpFileLoader::class)
        ->args([
            service('file_locator'),
            param('kernel.environment'),
        ])
        ->tag('babdev_websocket_server.routing.loader')
    ;

    $services->set('babdev_websocket_server.routing.loader.psr4', Psr4DirectoryLoader::class)
        ->args([
            service('file_locator'),
        ])
        ->tag('babdev_websocket_server.routing.loader', ['priority' => -10])
    ;

    $services->set('babdev_websocket_server.routing.loader.xml', XmlFileLoader::class)
        ->args([
            service('file_locator'),
            param('kernel.environment'),
        ])
        ->tag('babdev_websocket_server.routing.loader')
    ;

    $services->set('babdev_websocket_server.routing.loader.yml', YamlFileLoader::class)
        ->args([
            service('file_locator'),
            param('kernel.environment'),
        ])
        ->tag('babdev_websocket_server.routing.loader')
    ;

    $services->set('babdev_websocket_server.routing.resolver', LoaderResolver::class);

    $services->set('babdev_websocket_server.server.factory.default', DefaultServerFactory::class)
        ->args([
            service(MiddlewareStackBuilder::class),
            service(LoopInterface::class),
        ])
    ;
    $services->alias(ServerFactory::class, 'babdev_websocket_server.server.factory.default');

    $services->set('babdev_websocket_server.server.server_middleware.dispatch_to_message_handler', DispatchMessageToHandler::class)
        ->args([
            service('babdev_websocket_server.router'),
            service(MessageHandlerResolver::class),
            service('event_dispatcher')->nullOnInvalid(),
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

    $services->set('babdev_websocket_server.server.server_middleware.establish_websocket_connection', EstablishWebSocketConnection::class)
        ->args([
            abstract_arg('decorated middleware'),
            service('babdev_websocket_server.rfc6455.server_negotiator'),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -30])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.authenticate_user', AuthenticateUser::class)
        ->args([
            abstract_arg('decorated middleware'),
            service(Authenticator::class),
            service(TokenStorage::class),
        ])
        ->call('setLogger', [
            service('logger'),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -40])
        ->tag('monolog.logger', ['channel' => 'websocket'])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.initialize_session', InitializeSession::class)
        ->args([
            abstract_arg('decorated middleware'),
            abstract_arg('session factory'),
            service(OptionsHandler::class),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -50])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins', RestrictToAllowedOrigins::class)
        ->args([
            abstract_arg('decorated middleware'),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -60])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.parse_http_request', ParseHttpRequest::class)
        ->args([
            abstract_arg('decorated middleware'),
            service(RequestParser::class),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -70])
    ;

    $services->set('babdev_websocket_server.server.server_middleware.reject_blocked_ip_address', RejectBlockedIpAddress::class)
        ->args([
            abstract_arg('decorated middleware'),
        ])
        ->tag('babdev.websocket_server.server_middleware', ['priority' => -80])
    ;

    $services->set('babdev_websocket_server.server.options_handler', IniOptionsHandler::class);
    $services->alias(OptionsHandler::class, 'babdev_websocket_server.server.options_handler');

    $services->set('babdev_websocket_server.server.request_parser', GuzzleRequestParser::class)
        ->args([
            abstract_arg('max request size'),
        ])
    ;
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

    $services->set('babdev_websocket_server.socket_server.factory.default', DefaultSocketServerFactory::class)
        ->args([
            abstract_arg('server context'),
            service(LoopInterface::class),
        ])
    ;
    $services->alias(SocketServerFactory::class, 'babdev_websocket_server.socket_server.factory.default');
};
