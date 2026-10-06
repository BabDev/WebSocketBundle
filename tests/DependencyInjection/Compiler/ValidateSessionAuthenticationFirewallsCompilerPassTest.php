<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\ValidateSessionAuthenticationFirewallsCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\SessionAuthenticationProviderFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class ValidateSessionAuthenticationFirewallsCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testTheFirewallsParameterIsRequiredWhenUsingAllFirewalls(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "firewalls" config for the session authentication provider is not set and the "security.firewalls" container parameter has not been set.');

        $this->registerSessionProvider(null);

        $this->compile();
    }

    public function testTheContainerCompilesWhenTheFirewallsParameterIsSet(): void
    {
        $this->registerSessionProvider(null);

        $this->container->setParameter('security.firewalls', ['dev', 'main']);

        $this->compile();

        $this->assertContainerBuilderHasParameter('security.firewalls', ['dev', 'main']);
    }

    public function testTheFirewallsParameterIsNotRequiredWhenFirewallsAreConfigured(): void
    {
        $this->registerSessionProvider(['main']);

        $this->compile();

        self::assertSame(['main'], $this->container->getDefinition('babdev_websocket_server.authentication.provider.session.default')->getArgument(1));
    }

    /**
     * @param list<non-empty-string>|null $firewalls
     */
    private function registerSessionProvider(?array $firewalls): void
    {
        // The provider is created by its factory the same way as when the bundle extension is loaded
        $this->container->register('babdev_websocket_server.authentication.provider.session', SessionAuthenticationProvider::class)
            ->setArguments([null, null]);

        new SessionAuthenticationProviderFactory()->createAuthenticationProvider($this->container, ['firewalls' => $firewalls]);
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ValidateSessionAuthenticationFirewallsCompilerPass());
    }
}
