# Installation & Setup

To install this bundle, run the following [Composer](https://getcomposer.org/) command:

```bash
composer require babdev/websocket-bundle
```

## Register The Bundle

For an application using Symfony Flex the bundle should be automatically registered, but if not you will need to add it to your `config/bundles.php` file.

```php
<?php

return [
    // ...

    BabDev\WebSocketBundle\BabDevWebSocketBundle::class => ['all' => true],
];
```

## Configure The Bundle

Because a Flex recipe is not published for the bundle at this time, you will need to manually configure the bundle to fully enable its features. The below example can be written to `config/packages/babdev_websocket.yaml` in your application as a starting point:

```yaml
babdev_websocket:
  authentication:
    providers:
      session:
        firewalls: ~
  server:
    uri: '%env(BABDEV_WEBSOCKET_SERVER_URI)%'
    router:
      resource: '%kernel.project_dir%/config/websocket_router.yaml'
    session:
      handler_service_id: 'session.handler.pdo'
```

In this example, we:

- Enable the [session authentication provider](/open-source/packages/websocketbundle/docs/1.x/authentication) and allow sessions for all firewalls
- Configure the address the server will listen to connections on using the `BABDEV_WEBSOCKET_SERVER_URI` environment variable
- Configure the router resource for the websocket server
- Configure the session handler that is used to read session data for incoming connections (for our example, we are using a PDO session handler but this can be any shared resource such as the database or Redis)

### Configuring the WebSocket Server Address

The `babdev_websocket.server.uri` configuration node is used to define what address and port the websocket server process will listen for incoming connections on. The below snippet can be added to your application's `.env` file to define a sane default for local development, which will listen for connections on localhost at port 8080:

```properties
###> babdev/websocket-bundle ###
BABDEV_WEBSOCKET_SERVER_URI=127.0.0.1:8080
###< babdev/websocket-bundle ###
```

### Configuring the WebSocket Server Router

The websocket server uses the Symfony Routing component to power its routing implementation, which requires a second router service to be configured in your application. Most of this is taken care of already in the bundle, however, you will need to add a file in your application to define the routes for the websocket server. The below example, which is based on the default `config/routes.yaml` added to applications, can be saved to your application at `config/websocket_router.yaml`:

```yaml
controllers:
  resource:
    path: ../src/WebSocket/
    namespace: App\WebSocket
  type: attribute
```

Similar to the default router configuration, this will scan for the `AsMessageHandler` attribute on classes in your `src/WebSocket` directory.
