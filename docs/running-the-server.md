# Running the WebSocket Server

The websocket server is run with the `babdev:websocket-server:run` command:

```bash
php bin/console babdev:websocket-server:run
```

By default, the server listens for connections on the URI from the `babdev_websocket.server.uri` configuration (see the [installation documentation](/open-source/packages/websocketbundle/docs/1.x/installation)), which can be overridden with the command's argument:

```bash
php bin/console babdev:websocket-server:run 127.0.0.1:8081
```

The command runs until the server is stopped. In production, it is recommended to run it with a process manager (such as Supervisor or systemd) which restarts the server if it exits, as an unexpected error stops the server with a non-zero exit code.

## Stopping the Server

The server is stopped when the process receives a `SIGINT` (for example, when pressing Ctrl+C), `SIGTERM` (for example, when a container is stopped), or `SIGQUIT` signal. Handling these signals requires the `pcntl` PHP extension.

When a signal is received, the server shuts down gracefully: it stops accepting new connections, sends connected clients a "1001 Going Away" close frame, and waits for the open connections to close. Connections which have not closed when the shutdown timeout expires are forcibly closed. A second signal stops the server immediately.

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
  server:
    # The time, in seconds, to wait for connections to close, 5 seconds by default
    shutdown_timeout: 10
```

Setting the shutdown timeout to `null` stops the server immediately without waiting for connections to close.

The `BabDev\WebSocketBundle\Event\AfterServerClosed` event is dispatched once the server has stopped accepting new connections, and the `BabDev\WebSocketBundle\Event\AfterLoopStopped` event is dispatched once the server has stopped (see the [events documentation](/open-source/packages/websocketbundle/docs/1.x/events)).

## Managing Connections

The bundle provides several options to protect the server from clients which are slow or no longer responding.

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
  server:
    # The time, in seconds, a client has to send its HTTP request, 10 seconds by default
    request_timeout: 10
    # The interval, in seconds, at which connected clients are pinged, disabled by default
    keepalive: 30
    # The number of bytes which can be written to a client after its write buffer is full, 1 MiB by default
    write_buffer_limit: 1048576
```

- `request_timeout` closes connections which do not send a complete HTTP request in time with a "408 Request Timeout" response, including clients which send their request slowly
- `keepalive` pings connected clients at the given interval, and closes connections which have not responded to the previous ping
- `write_buffer_limit` closes the connection to a client which is not reading the data sent to it once this much data has been written to the connection after its write buffer is full, preventing it from exhausting the server's memory

Each of these options can be disabled by setting it to `null`.

The size of the messages received from clients can also be limited, see the [securing connections documentation](/open-source/packages/websocketbundle/docs/1.x/securing-connections).

## WAMP Protocol Options

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
  server:
    # Whether clients must request the "wamp" sub-protocol, enabled by default
    strict_sub_protocol_check: true
    # The maximum number of CURIE prefixes a client can register for its connection, 100 by default
    max_prefixes: 100
```

- `strict_sub_protocol_check` rejects clients which do not request the `wamp` sub-protocol in their `Sec-WebSocket-Protocol` header with a "426 Upgrade Required" response; disabling it allows these clients to connect
- `max_prefixes` limits the number of prefixes a client can register with the WAMP "PREFIX" message

## Using Environment Variables

The server options can be set with environment variables. As with other numeric configuration in Symfony, numeric options require a typed environment variable processor:

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
  server:
    request_timeout: '%env(float:WEBSOCKET_REQUEST_TIMEOUT)%'
    write_buffer_limit: '%env(int:WEBSOCKET_WRITE_BUFFER_LIMIT)%'
```
