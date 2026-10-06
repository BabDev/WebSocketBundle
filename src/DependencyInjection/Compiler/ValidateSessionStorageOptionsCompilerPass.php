<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * The validate session storage options compiler pass ensures the session options from the FrameworkBundle are available
 * when the websocket server reads sessions using a session handler.
 *
 * @internal
 */
final class ValidateSessionStorageOptionsCompilerPass implements CompilerPassInterface
{
    /**
     * @throws LogicException if the session handler is configured and the "session.storage.options" container parameter is missing
     */
    public function process(ContainerBuilder $container): void
    {
        // This service is only registered when the "server.session.handler_service_id" option is used
        if (!$container->hasDefinition('babdev_websocket_server.server.session.storage.factory.read_only_native')) {
            return;
        }

        if (!$container->hasParameter('session.storage.options')) {
            throw new LogicException('The "server.session.handler_service_id" option requires sessions to be enabled in the FrameworkBundle configuration ("framework.session"), as the websocket server reads sessions using the same options. To read sessions without the FrameworkBundle session configuration, use the "server.session.factory_service_id" or "server.session.storage_factory_service_id" option instead.');
        }
    }
}
