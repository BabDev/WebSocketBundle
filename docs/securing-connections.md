# Securing the WebSocket Server

Thanks to the features available in the WebSocket Server library, the bundle can be configured to provide extra security checks to block unwelcome traffic by checking the origin or IP address.

Note, it is recommended these types of checks are performed at a higher level in your application stack, such as a reverse proxy or load balancer, but these features are available for ease of use.

## Restricting Allowed Origins

The bundle can be configured to only allow requests when traffic originates from a list of allowed origins, which are compared against the `Origin` header of the HTTP request.

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
  server:
    # A list of origins allowed to connect to the websocket server, each entry can be either a full origin (such as "https://example.com:8443") which must match the scheme, host, and port of the "Origin" header of the HTTP request, or a host (such as "example.com") which matches the host with any scheme or port.
    allowed_origins:
      - https://www.example.com
      - https://example.com
      - localhost
```

Each allowed origin can be given in one of two formats:

- A full origin, such as `https://example.com` or `http://localhost:8080`, which only allows connections whose `Origin` header has the same scheme, host, and port; the default port for the `http`, `https`, `ws`, and `wss` schemes may be omitted, so `https://example.com` and `https://example.com:443` are equivalent
- A host, such as `example.com`, which allows connections whose `Origin` header has the same host with any scheme and port

Hosts are compared case-insensitively, and a host does not match its subdomains (`example.com` does not allow `https://www.example.com`). Using a full origin is recommended, as a host also allows connections from pages served over an insecure `http` scheme or from another port on the same host.

With this configuration, only connections from `https://www.example.com`, `https://example.com`, and any scheme or port on `localhost` will be accepted, others will be rejected.

An allowed origin which is not in one of these formats, such as a host with a port (`localhost:8080`) or an origin with a path (`https://example.com/path`), causes an error when the container is compiled.

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

## Running Behind a Reverse Proxy

When the websocket server is behind a reverse proxy (such as nginx or a load balancer), every connection comes from the proxy, so the IP address checks above would only see the proxy's address. The bundle can be configured to trust the forwarding headers set by your proxies, which replaces the connection's address with the client's IP address before the blocked IP addresses are checked.

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
  server:
    trusted_proxies:
      - 192.0.2.1
      - 10.0.0.0/8
    # Defaults to x-forwarded-for
    trusted_headers:
      - x-forwarded-for
```

These options use the same formats as the [`framework.trusted_proxies` and `framework.trusted_headers`](https://symfony.com/doc/current/deployment/proxies.html) options, so the same values can be shared between your application and the websocket server, including through an environment variable with a comma-separated list:

```yaml
# config/packages/babdev_websocket.yaml
babdev_websocket:
  server:
    trusted_proxies: '%env(SYMFONY_TRUSTED_PROXIES)%'
```

The trusted proxies can be single addresses, CIDR ranges, `PRIVATE_SUBNETS` to trust all private network ranges, or `REMOTE_ADDR` to trust every connection (only use this when the server can only be reached through the proxy).

Only the `forwarded` and `x-forwarded-for` headers are used to resolve the client's IP address, any other supported header is accepted and ignored. Only trust the headers your proxy sets, as a proxy which sets one of these headers usually passes the other through from the client unchanged, allowing clients to send any IP address. A trusted header which is not supported causes an error when the websocket server is started.
