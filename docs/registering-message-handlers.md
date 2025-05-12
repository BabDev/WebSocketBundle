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

### Topic Message Handler

This example handler will simply echo the provided event to all subscribers connected to a topic.

```php
<?php declare(strict_types=1);

namespace App\WebSocket\MessageHandler;

use BabDev\WebSocket\Server\Connection;
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
        $topic->broadcast(['msg' => 'Connection opened'], [], [$connection->getAttributeStore()->get('wamp.session_id')]);
    }

    public function onUnsubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void
    {
        $topic->broadcast(['msg' => 'Connection closed'], [], [$connection->getAttributeStore()->get('wamp.session_id')]);
    }

    /**
     * Handles a "PUBLISH" WAMP message from the client.
     *
     * @param array|string $event    The event payload for the message
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     */
    public function onPublish(Connection $connection, Topic $topic, WAMPMessageRequest $request, array|string $event, array $exclude, array $eligible): void
    {
        $topic->broadcast($event, $exclude, $eligible);
    }
}
```

## Configuring the WebSocket Router

The websocket server uses the [Symfony Routing Component](https://symfony.com/doc/current/routing.html) to manage its routes. This means that with one exception (the attribute class used on message handlers), all configuration for the Symfony router applies to the websocket server as well.

When using attributes to configure routes, the `BabDev\WebSocketBundle\Attribute\AsMessageHandler` class should be used instead of the `Symfony\Component\Routing\Attribute\Route` class.

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
