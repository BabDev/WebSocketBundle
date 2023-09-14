# Authentication

The bundle automatically handles authenticating users on every connection to your websocket server by registering additional middleware with the server, allowing your message handlers to use your authenticated user data.

## Authentication Providers

An authentication provider is an implementation of `BabDev\WebSocketBundle\Authentication\Provider\AuthenticationProvider` which processes a `BabDev\WebSocket\Server\Connection` and creates a security token representing the current user.

A provider is required to have two methods:

- `supports()` - Determines if the provider can authenticate the given connection
- `authenticate()` - Authenticates the connection

### Session Authentication

The bundle provides a session authentication provider which will authenticate users using their HTTP session from your website's frontend.

To enable the session authenticator, you must add it to the `providers` list in the authentication configuration and configure the session handler that will be used. In this example, your Symfony application will use the PDO session handler.

```yaml
services:
    session.handler.pdo:
        class: Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler
        arguments:
            - !service { class: PDO, factory: ['@database_connection', 'getWrappedConnection'] }
            - { lock_mode: 0 }

framework:
    session:
        handler_id: 'session.handler.pdo'

babdev_websocket:
    authentication:
        providers:
            session:
                firewalls: ~
    server:
        session:
            handler_service_id: 'session.handler.pdo'
```

Configuring the session handler will add the [`InitializeSession` middleware](/open-source/packages/websocket-server/docs/1.x/middleware/initialize-session) to the websocket server which will provide a read-only interface for the session data from your website.

By default, the session authentication provider will attempt to authenticate to any of the firewalls set in your `security.firewalls` configuration in the same order which the firewalls are defined. You can specify the firewall(s) to use with the `firewall` configuration key on the session provider.

```yaml
babdev_websocket:
    authentication:
        providers:
            session:
                firewalls: ['main'] # This can be an array to specify multiple firewalls or a string when specifying a single firewall 
```

### Provider Priority

When providers are registered to the authenticator service, they are then used in a "first in, first out" order, meaning the order they are triggered will be the same order they are configured in. Assuming your application has multiple authenticators and you want a custom authenticator to be attempted before the session authenticator, you would use the below configuration to do so:

```yaml
babdev_websocket:
    authentication:
        providers:
            custom: ~
            session: ~
```

### Registering New Authenticators

In addition to creating a class implementing `BabDev\WebSocketBundle\Authentication\Provider\AuthenticationProvider`, you must also register the authenticator with a `BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\AuthenticationProviderFactory` to the bundle's container extension. Similar to factories used by Symfony's `SecurityBundle`, this factory is used to allow you to configure the authenticator for your application and build the authentication provider service. 

A factory is required to have two methods:

- `getKey()` - A unique name to identify the provider in the application configuration, this name is used as the key in the `providers` list
- `addConfiguration()` - Defines the configuration nodes (if any are required) for the authenticator
- `createAuthenticationProvider()` - Registers the authentication provider service to the dependency injection container and returns the provider's service ID

The factory must be registered to this bundle's container extension when the container is being built. Typically, this would be in the `build()` method of your application's `Kernel` or a bundle's `Bundle` class.

```php
<?php

namespace App;

use App\DependencyInjection\Factory\Authentication\CustomAuthenticationProviderFactory;
use BabDev\WebSocketBundle\DependencyInjection\BabDevWebSocketExtension;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        /** @var BabDevWebSocketExtension $extension */
        $extension = $container->getExtension('babdev_websocket');
        $extension->addAuthenticationProviderFactory(new CustomAuthenticationProviderFactory());
    }
}
```

## Storing Tokens

After authentication is complete, the token is stored in a `BabDev\WebSocketBundle\Authentication\Storage\TokenStorage` instance. The default implementation uses a `BabDev\WebSocketBundle\Authentication\Storage\Driver\StorageDriver` as an abstraction layer for where authentication tokens are stored.

By default, the bundle provides and uses an in-memory storage driver. You can provide your own driver implementation by creating a class implementing the driver interface and updating the service container to point the '`BabDev\WebSocketBundle\Authentication\Storage\Driver\StorageDriver`' alias to your implementation.

## Fetching Tokens

The `BabDev\WebSocketBundle\Authentication\ConnectionRepository` provides several helper methods for querying the token storage to find the connections and tokens for any connected user. For example, this repository could be used to find all authenticated users connected to a given topic to send a message.

### Token Connection DTO

The `BabDev\WebSocketBundle\Authentication\TokenConnection` object is a DTO which is returned by many of the repository methods and contains the `BabDev\WebSocket\Server\Connection` and its security token from the authenticator. 

### Retrieving All Connections For A Topic

The `findAll()` method is used to find all connections for a given topic. The method has an optional `$anonymous` parameter which can be used to filter out connections for unauthenticated users. The list will be returned as an array of `BabDev\WebSocketBundle\Authentication\TokenConnection` objects.

### Retrieving All Connections For A Username

The `findAllByUsername()` method is used to find all connections for a user with the given username. This is helpful if a user has multiple active connections (i.e. has multiple tabs in their browser open). The list will be returned as an array of `BabDev\WebSocketBundle\Authentication\TokenConnection` objects.

### Retrieving All Connections For A User With A Role

The `findAllWithRoles()` method is used to find all connections for a user who has any of the given roles. Note that this method checks the list of roles on the underlying security token and does not use the site's role hierarchy. The list will be returned as an array of `BabDev\WebSocketBundle\Authentication\TokenConnection` objects.

### Retrieving The Token For A Connection

The `findTokenForConnection()` method is used to find the security token for the given connection.

### Retrieving The User For A Connection

The `getUser()` method is used to retrieve the user for the given connection. This is a shortcut for `$repository->findTokenForConnection($token)->getUser()`.

### Checking For A Connection By Username

The `hasConnectionForUsername()` method is used to determine if there is a connection for the given username and will return true when the first connection matches.
