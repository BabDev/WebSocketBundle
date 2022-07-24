# Subscribing to Bundle Events

The WebSocketBundle and the parent library provide several events which can be used to hook into actions.

## Available Events

### Library Events

- `BabDev\WebSocket\Server\Connection\Event\ConnectionClosed` - dispatched when a client has closed their connection
- `BabDev\WebSocket\Server\Connection\Event\ConnectionError` - dispatched when there is a client error or an unhandled exception on the server
- `BabDev\WebSocket\Server\Connection\Event\ConnectionOpened` - dispatched when a new client has connected to the server

### Bundle Events

- `BabDev\WebSocketBundle\Event\AfterLoopStopped` - dispatched after the event loop has been stopped
- `BabDev\WebSocketBundle\Event\AfterServerClosed` - dispatched after the shutdown signal has been received but before the event loop is stopped
- `BabDev\WebSocketBundle\Event\BeforeRunServer` - dispatched before the websocket server is started

## Creating an event listener

To create an event listener, please follow the [Symfony documentation](https://symfony.com/doc/current/event_dispatcher.html).
