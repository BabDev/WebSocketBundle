# Securing the WebSocket Server

Thanks to the features available in the WebSocket Server library, the bundle can be configured to provide extra security checks to block unwelcome traffic by checking the origin or IP address.

Note, it is recommended these types of checks are performed at a higher level in your application stack, such as a reverse proxy or load balancer, but these features are available for ease of use.

## Restricting Allowed Origins

The bundle can be configured to only allow requests when traffic originates from a list of allowed domains.

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
    server:
        # A list of origins allowed to connect to the websocket server, must match the value from the "Origin" header of the HTTP request.
        allowed_origins:
            - www.example.com
            - example.com
```

With this configuration, only connections from `www.example.com` and `example.com` will be accepted, others will be rejected.

## Blocking IP Addresses

The bundle can be configured to block traffic from specified IP addresses. The configuration accepts both single addresses and network ranges in both IPv4 and IPv6 format.

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
    server:
      # A list of IP addresses which are not allowed to connect to the websocket server, each entry can be either a single address or a CIDR range.
        blocked_ip_addresses:
            - 8.8.8.8
            - 192.168.1.0/24
```

With this configuration, all connections from `8.8.8.8` and the `192.168.1.0/24` range will be rejected.
