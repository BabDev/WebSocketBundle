# Managing Middleware

By default, this bundle registers [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) by finding all services with the `babdev.websocket_server.server_middleware` tag and sorting them by priority.

Below is the list of middleware provided by this bundle and the library and their default priorities:

| Middleware Class                                                            | Service ID                                                                        | Priority |
|-----------------------------------------------------------------------------|-----------------------------------------------------------------------------------|----------|
| `BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler`          | `babdev_websocket_server.server.server_middleware.dispatch_to_message_handler`    | 0        |
| `BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions`          | `babdev_websocket_server.server.server_middleware.update_topic_subscriptions`     | -10      |
| `BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage`                  | `babdev_websocket_server.server.server_middleware.parse_wamp_message`             | -20      |
| `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` | `babdev_websocket_server.server.server_middleware.establish_websocket_connection` | -30      |
| `BabDev\WebSocketBundle\Server\Middleware\AuthenticateUser`                 | `babdev_websocket_server.server.server_middleware.authenticate_user`              | -40      |
| `BabDev\WebSocket\Server\Session\Middleware\InitializeSession`              | `babdev_websocket_server.server.server_middleware.initialize_session`             | -50      |
| `BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins`          | `babdev_websocket_server.server.server_middleware.restrict_to_allowed_origins`    | -60      |
| `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest`                  | `babdev_websocket_server.server.server_middleware.parse_http_request`             | -70      |
| `BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress`            | `babdev_websocket_server.server.server_middleware.reject_blocked_ip_address`      | -80      |

## Adding Middleware

New middleware should be added with a priority lower than 0 as the `BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler` middleware should be the last middleware in the stack.

Middleware must have the decorated middleware as the first parameter in the constructor, and cannot use a named parameter in the service definition (i.e. `$middleware: !abstract decorated middleware` for YAML); this is required for the compiler pass which builds the middleware stack to correctly set the arguments.

The bundle supports autoconfiguration of `BabDev\WebSocket\Server\ServerMiddleware` classes using the `#[AsServerMiddleware]` attribute on your middleware class.

```php
<?php declare(strict_types=1);

namespace App\WebSocket\Middleware;

use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocketBundle\Attribute\AsServerMiddleware;

#[AsServerMiddleware(priority: -75)]
final readonly class EarlyMiddleware implements ServerMiddleware
{
    public function __construct(
        private ServerMiddleware $middleware,
    ) {}

    /* Class implementation */
}
```

If you are not using autoconfiguration, the service should be tagged with the `babdev.websocket_server.server_middleware` service tag and the priority specified.

```yaml
# config/services.yaml
services:
    App\WebSocket\Middleware\EarlyMiddleware:
        arguments:
            - !abstract decorated middleware
        tags:
            - { name: babdev.websocket_server.server_middleware, priority: -75 }
```
