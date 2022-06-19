<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class BabDevWebSocketExtension extends ConfigurableExtension
{
    public function getAlias(): string
    {
        return 'babdev_websocket';
    }

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
    }
}
