<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;

final class SessionAuthenticationProviderFactory implements AuthenticationProviderFactory
{
    /**
     * Defines the configuration key used to reference the provider in the configuration.
     *
     * @return non-empty-string
     */
    public function getKey(): string
    {
        return 'session';
    }

    /**
     * Defines the priority at which the authentication provider is called.
     */
    public function getPriority(): int
    {
        return 0;
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

    /**
     * Creates the authentication provider service for the provided configuration.
     *
     * @param array{firewalls: list<non-empty-string>|non-empty-string|null} $config
     *
     * @return non-empty-string The authentication provider service ID to be used
     *
     * @throws RuntimeException if the firewalls node is not configured and the "security.firewalls" container parameter is missing
     */
    public function createAuthenticationProvider(ContainerBuilder $container, array $config): string
    {
        if (\is_array($config['firewalls'])) {
            $firewalls = $config['firewalls'];
        } elseif (\is_string($config['firewalls'])) {
            $firewalls = [$config['firewalls']];
        } else {
            if (!$container->hasParameter('security.firewalls')) {
                throw new RuntimeException('The "firewalls" config for the session authentication provider is not set and the "security.firewalls" container parameter has not been set. Ensure the SecurityBundle is configured or set a list of firewalls to use.');
            }

            $firewalls = new Parameter('security.firewalls');
        }

        $providerId = 'babdev_websocket_server.authentication.provider.session.default';

        $container->setDefinition($providerId, new ChildDefinition('babdev_websocket_server.authentication.provider.session'))
            ->replaceArgument(1, $firewalls);

        return $providerId;
    }
}
