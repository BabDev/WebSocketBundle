<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * The resolve session authentication firewalls compiler pass converts the firewalls used by the session authentication
 * provider to the security contexts their tokens are stored in the session with.
 *
 * @internal
 */
final class ResolveSessionAuthenticationFirewallsCompilerPass implements CompilerPassInterface
{
    private const string PROVIDER_ID = 'babdev_websocket_server.authentication.provider.session.default';

    /**
     * @throws RuntimeException if the session authentication provider uses all firewalls and the "security.firewalls" container parameter is missing
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::PROVIDER_ID)) {
            return;
        }

        $provider = $container->getDefinition(self::PROVIDER_ID);

        /** @var list<string>|Parameter $firewalls */
        $firewalls = $provider->getArgument(0);

        if ($firewalls instanceof Parameter) {
            if (!$container->hasParameter((string) $firewalls)) {
                throw new RuntimeException('The "firewalls" config for the session authentication provider is not set and the "security.firewalls" container parameter has not been set. Ensure the SecurityBundle is configured or set a list of firewalls to use.');
            }

            /** @var list<string> $firewalls */
            $firewalls = $container->getParameter((string) $firewalls);
        }

        $contexts = [];

        foreach ($firewalls as $firewall) {
            // Without the firewall's configuration, the configured value is used as the context
            if (!$container->hasDefinition($configId = 'security.firewall.map.config.'.$firewall)) {
                $contexts[] = $firewall;

                continue;
            }

            if (null !== $context = $this->getFirewallContext($container->getDefinition($configId))) {
                $contexts[] = $context;
            }
        }

        $provider->replaceArgument(0, array_values(array_unique($contexts)));
    }

    /**
     * Reads the context from the definition of a {@see \Symfony\Bundle\SecurityBundle\Security\FirewallConfig} instance.
     */
    private function getFirewallContext(Definition $config): ?string
    {
        try {
            $context = $config->getArgument(6);
        } catch (OutOfBoundsException) {
            // The context is not set for firewalls with security disabled
            return null;
        }

        return \is_string($context) ? $context : null;
    }
}
