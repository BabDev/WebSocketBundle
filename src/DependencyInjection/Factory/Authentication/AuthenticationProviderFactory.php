<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

interface AuthenticationProviderFactory
{
    /**
     * Creates the authentication provider service for the provided configuration.
     *
     * @return string The authentication provider service ID to be used
     */
    public function createAuthenticationProvider(ContainerBuilder $container, array $config): string;

    /**
     * Defines the configuration key used to reference the provider in the configuration.
     */
    public function getKey(): string;

    public function addConfiguration(NodeDefinition $builder): void;
}
