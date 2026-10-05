# Subscribing to Bundle Events

The WebSocketBundle and the parent library provide several events which can be used to hook into actions.

## Available Events

### Library Events

- `BabDev\WebSocket\Server\Connection\Event\ConnectionClosed` - dispatched when a client has closed their connection
- `BabDev\WebSocket\Server\Connection\Event\ConnectionError` - dispatched when there is a client error or an unhandled exception on the server
- `BabDev\WebSocket\Server\Connection\Event\ConnectionOpened` - dispatched when a new client has connected to the server

### Bundle Events

- `BabDev\WebSocketBundle\Event\AfterLoopStopped` - dispatched after the event loop has stopped and the websocket server is no longer running
- `BabDev\WebSocketBundle\Event\AfterServerClosed` - dispatched after a shutdown signal has been received and the server has stopped accepting new connections, while open connections are still being closed
- `BabDev\WebSocketBundle\Event\BeforeRunServer` - dispatched before the websocket server is started

## Creating an event listener

To create an event listener, please follow the [Symfony documentation](https://symfony.com/doc/current/event_dispatcher.html).
