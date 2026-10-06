<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\DependencyInjection\Compiler;

use BabDev\WebSocketBundle\Authentication\Provider\SessionAuthenticationProvider;
use BabDev\WebSocketBundle\DependencyInjection\Compiler\ConfigureSessionAuthenticationProviderCompilerPass;
use BabDev\WebSocketBundle\DependencyInjection\Factory\Authentication\SessionAuthenticationProviderFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

final class ConfigureSessionAuthenticationProviderCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testTheFirewallsParameterIsRequiredWhenUsingAllFirewalls(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The "firewalls" config for the session authentication provider is not set and the "security.firewalls" container parameter has not been set. Ensure the SecurityBundle is configured or set a list of firewalls to use.');

        $this->registerSessionProvider(null);

        $this->compile();
    }

    public function testAllFirewallsAreResolvedToTheContextsStoringATokenInTheSession(): void
    {
        $this->registerSessionProvider(null);

        $this->container->setParameter('security.firewalls', ['dev', 'admin', 'main', 'api']);

        $this->registerFirewall('dev', securityEnabled: false);
        $this->registerFirewall('admin', 'shared');
        $this->registerFirewall('main', 'shared');
        $this->registerFirewall('api', null);

        $this->compile();

        self::assertSame(['shared'], $this->getResolvedFirewalls(), 'Firewalls sharing a context should be read once, and firewalls without a context skipped.');
    }

    public function testConfiguredFirewallsAreResolvedToTheirContexts(): void
    {
        $this->registerSessionProvider(['main', 'secondary']);

        $this->registerFirewall('main', 'shared');
        $this->registerFirewall('secondary', 'secondary');

        $this->compile();

        self::assertSame(['shared', 'secondary'], $this->getResolvedFirewalls());
    }

    public function testAConfiguredValueWithoutAFirewallIsUsedAsTheContext(): void
    {
        $this->registerSessionProvider(['shared']);

        $this->compile();

        self::assertSame(['shared'], $this->getResolvedFirewalls());
    }

    public function testTheUserProvidersFromTheSecurityBundleAreUsed(): void
    {
        $this->registerSessionProvider(['main']);

        $userProviders = new IteratorArgument([new Reference('security.user.provider.concrete.users')]);

        // The SecurityBundle sets the user providers as the second argument of the context listener
        $this->container->register('security.context_listener', 'Symfony\Component\Security\Http\Firewall\ContextListener')
            ->setArguments([null, $userProviders]);

        $this->compile();

        self::assertSame($userProviders, $this->container->getDefinition('babdev_websocket_server.authentication.provider.session.default')->getArgument(2));
    }

    public function testNoUserProvidersAreUsedWithoutTheSecurityBundle(): void
    {
        $this->registerSessionProvider(['main']);

        $this->compile();

        self::assertSame([], $this->container->getDefinition('babdev_websocket_server.authentication.provider.session')->getArgument(2));
        self::assertArrayNotHasKey('index_2', $this->container->getDefinition('babdev_websocket_server.authentication.provider.session.default')->getArguments());
    }

    /**
     * @param list<non-empty-string>|null $firewalls
     */
    private function registerSessionProvider(?array $firewalls): void
    {
        // The provider is created by its factory the same way as when the bundle extension is loaded
        $this->container->register('babdev_websocket_server.authentication.provider.session', SessionAuthenticationProvider::class)
            ->setArguments([null, null, []]);

        new SessionAuthenticationProviderFactory()->createAuthenticationProvider($this->container, ['firewalls' => $firewalls]);
    }

    /**
     * Registers a firewall configuration the same way as the SecurityBundle.
     */
    private function registerFirewall(string $name, ?string $context = null, bool $securityEnabled = true): void
    {
        $config = $this->container->setDefinition('security.firewall.map.config.'.$name, new ChildDefinition('security.firewall.config'))
            ->replaceArgument(0, $name)
            ->replaceArgument(3, $securityEnabled);

        // The SecurityBundle does not set the context for firewalls with security disabled
        if ($securityEnabled) {
            $config->replaceArgument(6, $context);
        }
    }

    /**
     * @return mixed The firewalls argument of the session authentication provider
     */
    private function getResolvedFirewalls(): mixed
    {
        return $this->container->getDefinition('babdev_websocket_server.authentication.provider.session.default')->getArgument(0);
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ConfigureSessionAuthenticationProviderCompilerPass());
    }
}
