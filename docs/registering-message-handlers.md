# Registering Message Handlers

At its core, the websocket server uses [message handlers](/open-source/packages/websocket-server/docs/1.x/message-handler) to handle incoming WAMP messages. Message handlers are the last classes to be called in the websocket server's middleware stack and are directly responsible for processing the incoming request.

## Example Handlers

The below classes are simplified examples of message handlers covering both RPC and Topic (PubSub) handlers. In these examples, we are using the bundle's `AsMessageHandler` attribute to autoconfigure the service and define the route definition for the websocket server's router.

### RPC Message Handler

This example handler will add all the numeric values provided in the parameters and return the result to the connected client.

```php
<?php declare(strict_types=1);

namespace App\WebSocket\MessageHandler;

use BabDev\WebSocket\Server\RPCMessageHandler;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use BabDev\WebSocketBundle\Attribute\AsMessageHandler;

#[AsMessageHandler(path: '/arithmetic/add')]
final class AddValuesMessageHandler implements RPCMessageHandler
{
    /**
     * Handles an RPC "CALL" WAMP message from the client.
     *
     * @param string $id The unique ID of the RPC, required to send a "CALLERROR" or "CALLRESULT" message
     */
    public function onCall(WAMPConnection $connection, string $id, WAMPMessageRequest $request, array $params): void
    {
        $connection->callResult($id, ['sum' => array_sum($params)]);
    }
}
```

When the `onCall()` method throws an exception, the server sends the client a "CALLERROR" message with the `internal-error` error URI before handling the exception, so the client is always told the call failed. A message handler which sends its own error with `$connection->callError()` should return instead of also throwing an exception, otherwise the client receives two errors for the call.

### Topic Message Handler

This example handler will simply echo the provided event to all subscribers connected to a topic.

```php
<?php declare(strict_types=1);

namespace App\WebSocket\MessageHandler;

use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\TopicMessageHandler;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use BabDev\WebSocketBundle\Attribute\AsMessageHandler;

#[AsMessageHandler(path: '/echo')]
final class EchoMessageHandler implements TopicMessageHandler
{
    public function onSubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void
    {
        $topic->broadcast(['msg' => 'Connection opened'], [], [$connection->getAttributeStore()->get(AttributeKey::WAMP_SESSION_ID)]);
    }

    public function onUnsubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void
    {
        $topic->broadcast(['msg' => 'Connection closed'], [], [$connection->getAttributeStore()->get(AttributeKey::WAMP_SESSION_ID)]);
    }

    /**
     * Handles a "PUBLISH" WAMP message from the client.
     *
     * @param mixed        $event    The event payload for the message, which may be any decoded JSON value
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     */
    public function onPublish(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request, mixed $event, array $exclude, array $eligible): void
    {
        $topic->broadcast($event, $exclude, $eligible);
    }
}
```

## Configuring the WebSocket Router

The websocket server uses the [Symfony Routing Component](https://symfony.com/doc/current/routing.html) to manage its routes. This means that with one exception (the attribute class used on message handlers), all configuration for the Symfony router applies to the websocket server as well.

When using attributes to configure routes, the `BabDev\WebSocketBundle\Attribute\AsMessageHandler` class should be used instead of the `Symfony\Component\Routing\Attribute\Route` class.

### Matching URIs with Prefixes

Clients may register a prefix with the WAMP "PREFIX" message and use it in URIs as a CURIE (such as `chat:room`). Following the WAMP specification, a CURIE is resolved by appending the part after the prefix to the prefix's URI exactly as registered, and the resolved URI is matched against your routes. For example, when a client registers the `chat` prefix for `/chat/`, the `chat:room` URI resolves to `/chat/room` and is handled by a message handler with the `/chat/room` path. As nothing is added between the prefix and the rest of the URI, clients should include a trailing separator in the prefix URI.

### Route Parameters

The parameters of the matched route, such as the placeholders in its path, are available to the message handler from the attributes of the `BabDev\WebSocket\Server\WAMP\WAMPMessageRequest` object, both individually and as an array in the `_route_params` attribute:

```php
$room = $request->attributes->get('room');
```

### Localized Paths

As with controllers, the `AsMessageHandler` attribute supports a different path for each locale. Each path is handled by the same message handler, and the locale of the matched path is available in the `_locale` attribute of the request; the `_route` attribute contains the route name without the locale suffix.

```php
#[AsMessageHandler(path: ['en' => '/hello', 'fr' => '/bonjour'], name: 'greeting')]
```

Unlike an HTTP request, the locale is not applied to anything else, such as the translator, so a message handler which needs it must read it from the request attributes.

### Debugging the WebSocket Router

The bundle also provides support for debugging the websocket router with the `babdev:websocket-server:debug:router` command, which provides the same capabilities as the framework's `debug:router` command.

## Registering Message Handler Services

When using the `AsMessageHandler` attribute, the bundle will automatically tag message handler services to use in your application. If not using the attribute, services will need the `babdev_websocket_server.message_handler` service tag instead.

```yaml
# config/services.yaml
services:
  App\WebSocket\MessageHandler\EchoMessageHandler:
    tags:
      - { name: babdev_websocket_server.message_handler }
```

Message handlers are looked up by their service ID, using the `_controller` default of the matched route. Routes created from the `AsMessageHandler` attribute use the class name of the message handler, so the service ID must be the class name, which is the default for services registered through a resource in your service configuration. When a message handler service uses a different ID, define its route in a routing file and set the `controller` option to the service ID:

```yaml
# config/websocket_router.yaml
echo:
  path: /echo
  controller: app.websocket.echo_message_handler
```
