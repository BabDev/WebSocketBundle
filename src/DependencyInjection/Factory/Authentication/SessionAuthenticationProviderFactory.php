<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;

final class SessionAuthenticationProviderFactory implements AuthenticationProviderFactory
{
    /**
     * Creates the authentication provider service for the provided configuration.
     *
     * @return string The authentication provider service ID to be used
     *
     * @throws InvalidArgumentException if the firewalls node is an invalid type
     * @throws RuntimeException         if the firewalls node is not configured and the "security.firewalls" container parameter is missing
     */
    public function createAuthenticationProvider(ContainerBuilder $container, array $config): string
    {
        if (\is_array($config['firewalls'])) {
            $firewalls = $config['firewalls'];
        } elseif (\is_string($config['firewalls'])) {
            $firewalls = [$config['firewalls']];
        } elseif (null === $config['firewalls']) {
            if (!$container->hasParameter('security.firewalls')) {
                throw new RuntimeException('The "firewalls" config for the session authentication provider is not set and the "security.firewalls" container parameter has not been set. Ensure the SecurityBundle is configured or set a list of firewalls to use.');
            }

            $firewalls = new Parameter('security.firewalls');
        } else {
            throw new InvalidArgumentException(sprintf('The "firewalls" config must be an array, a string, or null; "%s" given.', get_debug_type($config['firewalls'])));
        }

        $providerId = 'babdev_websocket_server.authentication.provider.session.default';

        $container->setDefinition($providerId, new ChildDefinition('babdev_websocket_server.authentication.provider.session'))
            ->replaceArgument(1, $firewalls);

        return $providerId;
    }

    /**
     * Defines the configuration key used to reference the provider in the configuration.
     */
    public function getKey(): string
    {
        return 'session';
    }

    public function addConfiguration(NodeDefinition $builder): void
    {
        $builder->children()
            ->variableNode('firewalls')
                ->defaultNull()
                ->info('The firewalls from which the session token can be used; can be an array, a string, or null to allow all firewalls.')
                ->validate()
                    ->ifTrue(static fn ($firewalls): bool => !\is_array($firewalls) && !\is_string($firewalls) && null !== $firewalls)
                    ->thenInvalid('The firewalls node must be an array, a string, or null')
                ->end()
            ->end()
        ;
    }
}
