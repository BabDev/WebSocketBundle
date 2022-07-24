# Default Configuration

```yaml
babdev_websocket:
  authentication:
    providers:
      session:

        # The firewalls from which the session token can be used; can be an array, a string, or null to allow all firewalls.
        firewalls:            null
    storage:

      # The type of storage for the websocket server authentication tokens.
      type:                 in_memory # One of "in_memory"; "psr_cache"; "service", Required

      # The cache pool to use when using the PSR cache storage.
      pool:                 null

      # The service ID to use when using the service storage.
      id:                   null
  server:

    # The default URI to listen for connections on.
    uri:                  ~ # Required

    # Options used to configure the stream context, see the "React\Socket\SocketServer" class documentation for more details.
    context:              []

    # A list of origins allowed to connect to the websocket server, must match the value from the "Origin" header of the HTTP request.
    allowed_origins:      []

    # A list of IP addresses which are not allowed to connect to the websocket server, each entry can be either a single address or a CIDR range.
    blocked_ip_addresses: []
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
