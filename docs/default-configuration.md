# Default Configuration

```yaml
babdev_websocket:
  authentication:
    providers:
      session:

        # The firewalls from which the session token can be used; can be an array, a string, or null to allow all firewalls.
        firewalls:            null
  server:

    # An identifier for the websocket server, disclosed in the response to the WELCOME message from a WAMP client.
    identity:             BabDev-Websocket-Server/0.1

    # The maximum size of the HTTP request body, in bytes, that is allowed for incoming requests.
    max_http_request_size: 4096

    # The time, in seconds, a client has to send its HTTP request before the connection is closed with a "408 Request Timeout" response.
    request_timeout:      10.0

    # The number of bytes which can be written to a connection after its write buffer is full before the connection is closed.
    write_buffer_limit:   1048576

    # The time, in seconds, to wait for connections to close when the server is stopped with a shutdown signal before they are forcibly closed.
    shutdown_timeout:     5.0

    # The maximum size, in bytes, of a message received from a client before its connection is closed with a "1009 Message Too Big" close frame; null uses the default from the "ratchet/rfc6455" package (a quarter of the memory limit, unlimited when the memory limit is disabled) and 0 disables the limit.
    max_message_payload_size: null

    # The maximum size, in bytes, of a single frame received from a client before its connection is closed with a "1009 Message Too Big" close frame; null uses the default from the "ratchet/rfc6455" package (a quarter of the memory limit, unlimited when the memory limit is disabled) and 0 disables the limit.
    max_frame_payload_size: null

    # The maximum number of CURIE prefixes a client can register for its connection with the WAMP "PREFIX" message.
    max_prefixes:         100

    # Whether clients must request the "wamp" sub-protocol in their "Sec-WebSocket-Protocol" header, rejecting other clients with a "426 Upgrade Required" response.
    strict_sub_protocol_check: true

    # The default URI to listen for connections on.
    uri:                  ~ # Required

    # Options used to configure the stream context, see the "React\Socket\SocketServer" class documentation for more details.
    context:              []

    # A list of origins allowed to connect to the websocket server, each entry can be either a full origin (such as "https://example.com:8443") which must match the scheme, host, and port of the "Origin" header of the HTTP request, or a host (such as "example.com") which matches the host with any scheme or port.
    allowed_origins:      []

    # A list of IP addresses which are not allowed to connect to the websocket server, each entry can be either a single address or a CIDR range.
    blocked_ip_addresses: []

    # A list of reverse proxies trusted to forward the client IP address, using the same format as the "framework.trusted_proxies" option (single addresses, CIDR ranges, "PRIVATE_SUBNETS", or "REMOTE_ADDR"); can also be a comma-separated string, such as an environment variable.
    trusted_proxies:      []

    # The forwarding headers trusted from the trusted proxies, using the same format as the "framework.trusted_headers" option; only the "forwarded" and "x-forwarded-for" headers are used to resolve the client IP address.
    trusted_headers:

      # Default:
      - x-forwarded-for

    # The interval, in seconds, at which connected clients are pinged, closing connections which do not respond before the next ping.
    keepalive:            null
    periodic:
      dbal:

        # A list of "Doctrine\DBAL\Connection" services to ping.
        connections:          []

        # The interval, in seconds, which connections are pinged.
        interval:             60
    router:

      # The main routing resource to import when loading the websocket server route definitions.
      resource:             ~ # Required
    session:

      # A service ID for a "Symfony\Component\HttpFoundation\Session\SessionFactoryInterface" implementation to create the session service.
      factory_service_id:   ~

      # A service ID for a "Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface" implementation to create the session storage service, used with the default session factory.
      storage_factory_service_id: ~

      # A service ID for a "SessionHandlerInterface" implementation to create the session handler, used with the default session storage factory.
      handler_service_id:   ~
```
