<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * The validate session authentication firewalls compiler pass ensures the "security.firewalls" container parameter exists
 * when the session authentication provider is configured to use all firewalls.
 *
 * @internal
 */
final class ValidateSessionAuthenticationFirewallsCompilerPass implements CompilerPassInterface
{
    /**
     * @throws RuntimeException if the session authentication provider uses all firewalls and the "security.firewalls" container parameter is missing
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('babdev_websocket_server.authentication.provider.session.default')) {
            return;
        }

        $firewalls = $container->getDefinition('babdev_websocket_server.authentication.provider.session.default')->getArgument(1);

        if ($firewalls instanceof Parameter && 'security.firewalls' === (string) $firewalls && !$container->hasParameter('security.firewalls')) {
            throw new RuntimeException('The "firewalls" config for the session authentication provider is not set and the "security.firewalls" container parameter has not been set. Ensure the SecurityBundle is configured or set a list of firewalls to use.');
        }
    }
}
