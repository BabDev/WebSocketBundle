<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle;

use BabDev\WebSocketBundle\DependencyInjection\BabDevWebSocketExtension;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\BuildMiddlewareStackCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\PingDBALConnectionsCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\RoutingResolverCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\SessionAuthenticationProviderFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BabDevWebSocketBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BuildMiddlewareStackCompilerPass());
        $container->addCompilerPass(new PingDBALConnectionsCompilerPass());
        $container->addCompilerPass(new RoutingResolverCompilerPass());

        /** @var BabDevWebSocketExtension $extension */
        $extension = $container->getExtension('babdev_websocket');
        $extension->addAuthenticationProviderFactory(new SessionAuthenticationProviderFactory());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new BabDevWebSocketExtension();
        }

        return $this->extension ?: null;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
