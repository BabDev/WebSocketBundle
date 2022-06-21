<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use React\Socket\SocketServer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('babdev_websocket');

        $rootNode = $treeBuilder->getRootNode();

        $this->addServerSection($rootNode);

        return $treeBuilder;
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
