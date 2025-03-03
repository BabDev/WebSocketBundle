<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Compiler;

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Configures PSR-17 factories for services which require one.
 *
 * @internal
 */
final class ConfigureHttpFactoriesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->processServerNegotiator($container);
    }

    private function processServerNegotiator(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('babdev_websocket_server.rfc6455.server_negotiator')) {
            return;
        }

        // Based on the Flex recipes, if the application already has a response factory service, we'll prefer that
        if ($container->has(ResponseFactoryInterface::class)) {
            $container->getDefinition('babdev_websocket_server.rfc6455.server_negotiator')
                ->replaceArgument(1, new Reference(ResponseFactoryInterface::class));

            return;
        }

        // Since the `guzzle/psr7` package is a hard requirement of the server library, we'll use that as a fallback
        $container->getDefinition('babdev_websocket_server.rfc6455.server_negotiator')
            ->replaceArgument(1, $container->register('.babdev_websocket_server.psr17_response_factory', HttpFactory::class));
    }
}
