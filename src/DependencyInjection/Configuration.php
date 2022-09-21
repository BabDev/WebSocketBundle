<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\AuthenticationProviderFactory;
use Doctrine\DBAL\Connection;
use React\Socket\SocketServer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

final class Configuration implements ConfigurationInterface
{
    public const AUTHENTICATION_STORAGE_TYPE_IN_MEMORY = 'in_memory';
    public const AUTHENTICATION_STORAGE_TYPE_PSR_CACHE = 'psr_cache';
    public const AUTHENTICATION_STORAGE_TYPE_SERVICE = 'service';

    /**
     * @param AuthenticationProviderFactory[] $authenticationProviderFactories
     */
    public function __construct(private readonly array $authenticationProviderFactories)
    {
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('babdev_websocket');

        $rootNode = $treeBuilder->getRootNode();

        $this->addAuthenticationSection($rootNode);
        $this->addServerSection($rootNode);

        return $treeBuilder;
    }

    private function addAuthenticationSection(ArrayNodeDefinition $rootNode): void
    {
        $authenticationNode = $rootNode->children()
            ->arrayNode('authentication')
            ->addDefaultsIfNotSet();

        $this->addAuthenticationProvidersSection($authenticationNode);

        $authenticationNode->children()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('type')
                            ->isRequired()
                            ->defaultValue(self::AUTHENTICATION_STORAGE_TYPE_IN_MEMORY)
                            ->info('The type of storage for the websocket server authentication tokens.')
                            ->values([self::AUTHENTICATION_STORAGE_TYPE_IN_MEMORY, self::AUTHENTICATION_STORAGE_TYPE_PSR_CACHE, self::AUTHENTICATION_STORAGE_TYPE_SERVICE])
                        ->end()
                        ->scalarNode('pool')
                            ->defaultNull()
                            ->info('The cache pool to use when using the PSR cache storage.')
                        ->end()
                        ->scalarNode('id')
                            ->defaultNull()
                            ->info('The service ID to use when using the service storage.')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $config): bool => ('' === $config['pool'] || null === $config['pool']) && self::AUTHENTICATION_STORAGE_TYPE_PSR_CACHE === $config['type'])
                        ->thenInvalid('A cache pool must be set when using the PSR cache storage')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $config): bool => ('' === $config['id'] || null === $config['id']) && self::AUTHENTICATION_STORAGE_TYPE_SERVICE === $config['type'])
                        ->thenInvalid('A service ID must be set when using the service storage')
                    ->end()
                ->end()
            ->end()
        ->end();
    }

    private function addAuthenticationProvidersSection(ArrayNodeDefinition $authenticationNode): void
    {
        $providerNodeBuilder = $authenticationNode
            ->fixXmlConfig('provider')
            ->children()
                ->arrayNode('providers')
        ;

        foreach ($this->authenticationProviderFactories as $factory) {
            $name = str_replace('-', '_', $factory->getKey());
            $factoryNode = $providerNodeBuilder->children()->arrayNode($name)->canBeUnset();

            $factory->addConfiguration($factoryNode);
        }
    }

    private function addServerSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode->children()
            ->arrayNode('server')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('uri')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('The default URI to listen for connections on.')
                    ->end()
                    ->variableNode('context')
                        ->info(sprintf('Options used to configure the stream context, see the "%s" class documentation for more details.', SocketServer::class))
                        ->defaultValue([])
                    ->end()
                    ->arrayNode('allowed_origins')
                        ->info('A list of origins allowed to connect to the websocket server, must match the value from the "Origin" header of the HTTP request.')
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('blocked_ip_addresses')
                        ->info('A list of IP addresses which are not allowed to connect to the websocket server, each entry can be either a single address or a CIDR range.')
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('keepalive')
                        ->canBeEnabled()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('interval')
                                ->isRequired()
                                ->defaultValue(30)
                                ->info('The interval, in seconds, which connections are pinged.')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('periodic')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('dbal')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('connections')
                                        ->info(sprintf('A list of "%s" services to ping.', Connection::class))
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->integerNode('interval')
                                        ->isRequired()
                                        ->defaultValue(60)
                                        ->info('The interval, in seconds, which connections are pinged.')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('router')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('resource')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('The main routing resource to import when loading the websocket server route definitions.')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('session')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('factory_service_id')
                                ->info(sprintf('A service ID for a "%s" implementation to create the session service.', SessionFactoryInterface::class))
                            ->end()
                            ->scalarNode('storage_factory_service_id')
                                ->info(sprintf('A service ID for a "%s" implementation to create the session storage service, used with the default session factory.', SessionStorageFactoryInterface::class))
                            ->end()
                            ->scalarNode('handler_service_id')
                                ->info(sprintf('A service ID for a "%s" implementation to create the session handler, used with the default session storage factory.', \SessionHandlerInterface::class))
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static function (array $config): bool {
                                $options = 0;
                                $options += isset($config['factory_service_id']) ? 1 : 0;
                                $options += isset($config['storage_factory_service_id']) ? 1 : 0;
                                $options += isset($config['handler_service_id']) ? 1 : 0;

                                return $options > 1;
                            })
                            ->thenInvalid('You must only set one session service option')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
