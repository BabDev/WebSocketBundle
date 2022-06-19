<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use React\Socket\SocketServer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

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
                ->end()
            ->end()
        ;
    }
}
