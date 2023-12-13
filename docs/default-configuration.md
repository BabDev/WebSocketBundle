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

    # The default URI to listen for connections on.
    uri:                  ~ # Required

    # Options used to configure the stream context, see the "React\Socket\SocketServer" class documentation for more details.
    context:              []

    # A list of origins allowed to connect to the websocket server, must match the value from the "Origin" header of the HTTP request.
    allowed_origins:      []

    # A list of IP addresses which are not allowed to connect to the websocket server, each entry can be either a single address or a CIDR range.
    blocked_ip_addresses: []
    keepalive:
      enabled:              false

      # The interval, in seconds, which connections are pinged.
      interval:             30 # Required
    periodic:
      dbal:

        # A list of "Doctrine\DBAL\Connection" services to ping.
        connections:          []

        # The interval, in seconds, which connections are pinged.
        interval:             60 # Required
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
