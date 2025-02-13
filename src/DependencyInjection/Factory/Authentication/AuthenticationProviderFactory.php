<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

interface AuthenticationProviderFactory
{
    /**
     * Defines the configuration key used to reference the provider in the configuration.
     *
     * @return non-empty-string
     */
    public function getKey(): string;

    /**
     * Defines the priority at which the authentication provider is called.
     */
    public function getPriority(): int;

    public function addConfiguration(NodeDefinition $builder): void;

    /**
     * Creates the authentication provider service for the provided configuration.
     *
     * @return non-empty-string The authentication provider service ID to be used
     */
    public function createAuthenticationProvider(ContainerBuilder $container, array $config): string;
}
