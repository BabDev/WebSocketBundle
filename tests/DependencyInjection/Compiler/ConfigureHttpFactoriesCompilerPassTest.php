<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocketBundle\DependencyInjection\Compiler\ConfigureHttpFactoriesCompilerPass;
use GuzzleHttp\Psr7\HttpFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Http\Message\ResponseFactoryInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ConfigureHttpFactoriesCompilerPassTest extends AbstractCompilerPassTestCase
{
    #[TestDox('Uses the PSR-17 factory defined by the application')]
    public function testUsesThePsr17FactoryDefinedByTheApplication(): void
    {
        $this->container->register('babdev_websocket_server.rfc6455.server_negotiator', ServerNegotiator::class)
            ->setArguments([
                new Definition(RequestVerifier::class),
                new AbstractArgument('response factory'),
            ])
        ;

        $this->container->setAlias(ResponseFactoryInterface::class, HttpFactory::class);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument('babdev_websocket_server.rfc6455.server_negotiator', 1, new Reference(ResponseFactoryInterface::class));
    }

    public function testUsesTheGuzzleFactoryWhenNoFactoryIsDefinedByTheApplication(): void
    {
        $this->container->register('babdev_websocket_server.rfc6455.server_negotiator', ServerNegotiator::class)
            ->setArguments([
                new Definition(RequestVerifier::class),
                new AbstractArgument('response factory'),
            ])
        ;

        $this->compile();

        $this->assertContainerBuilderHasService('.babdev_websocket_server.psr17_response_factory');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument('babdev_websocket_server.rfc6455.server_negotiator', 1, $this->container->getDefinition('.babdev_websocket_server.psr17_response_factory'));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ConfigureHttpFactoriesCompilerPass());
    }
}
