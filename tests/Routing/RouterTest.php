<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Routing;

use BabDev\WebSocketBundle\Routing\Router;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouterTest extends TestCase
{
    public function testCannotInstantiateWithoutSymfonyContainerAndParameters(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You must either pass a "Symfony\Component\DependencyInjection\ContainerInterface" for the $container argument or provide the $parameters argument to the "BabDev\WebSocketBundle\Routing\Router" constructor.');

        new Router($this->createMock(ContainerInterface::class), 'foo');
    }

    public function testCanGenerateRoutesWhenTheRouteHasServiceParametersFromAParameterBag(): void
    {
        $routes = new RouteCollection();

        $routes->add(
            'foo',
            new Route(
                ' /{_locale}',
                [
                    '_locale' => '%locale%',
                ],
                [
                    '_locale' => 'en|es',
                ], [], '', [], [], '"%foo%" == "bar"'
            )
        );

        $container = $this->createPsr11Container($routes);

        $parameters = $this->createParameterBag([
            'locale' => 'es',
            'foo' => 'bar',
        ]);

        $router = new Router($container, 'foo', [], null, $parameters);

        $this->assertSame('/en', $router->generate('foo', ['_locale' => 'en']));
        $this->assertSame('/', $router->generate('foo', ['_locale' => 'es']));
        $this->assertSame('"bar" == "bar"', $router->getRouteCollection()->get('foo')->getCondition());
    }

    public function testCanGenerateRoutesWhenTheRouteHasServiceParametersFromASymfonyContainer(): void
    {
        $routes = new RouteCollection();

        $routes->add(
            'foo',
            new Route(
                ' /{_locale}',
                [
                    '_locale' => '%locale%',
                ],
                [
                    '_locale' => 'en|es',
                ], [], '', [], [], '"%foo%" == "bar"'
            )
        );

        $container = $this->createSymfonyContainer($routes);

        $parameters = $this->createParameterBag([
            'locale' => 'es',
            'foo' => 'bar',
        ]);

        $router = new Router($container, 'foo', [], null, $parameters);

        $this->assertSame('/en', $router->generate('foo', ['_locale' => 'en']));
        $this->assertSame('/', $router->generate('foo', ['_locale' => 'es']));
        $this->assertSame('"bar" == "bar"', $router->getRouteCollection()->get('foo')->getCondition());
    }

    public function testResolvesRequirementsPlaceholdersFromAParameterBag(): void
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            [
            ],
            [
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
            ]
        ));

        $container = $this->createPsr11Container($routes);

        $parameters = $this->createParameterBag([
            'parameter.foo' => 'foo',
            'parameter.bar' => 'bar',
        ]);

        $router = new Router($container, 'foo', [], null, $parameters);

        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            [
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
            ],
            $route->getRequirements(),
        );
    }

    public function testResolvesRequirementsPlaceholdersFromASymfonyContainer(): void
    {
        $routes = new RouteCollection();

        $routes->add('foo', new Route(
            '/foo',
            [
            ],
            [
                'foo' => 'before_%parameter.foo%',
                'bar' => '%parameter.bar%_after',
                'baz' => '%%escaped%%',
            ]
        ));

        $container = $this->createPsr11Container($routes);

        $parameters = $this->createParameterBag([
            'parameter.foo' => 'foo',
            'parameter.bar' => 'bar',
        ]);

        $router = new Router($container, 'foo', [], null, $parameters);

        $route = $router->getRouteCollection()->get('foo');

        $this->assertEquals(
            [
                'foo' => 'before_foo',
                'bar' => 'bar_after',
                'baz' => '%escaped%',
            ],
            $route->getRequirements(),
        );
    }

    public function testThrowsExceptionOnNonStringParameterFromAParameterBag(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The container parameter "object", used in the route configuration value "/%object%", must be a string or numeric, but it is of type "stdClass".');

        $routes = new RouteCollection();
        $routes->add('foo', new Route('/%object%'));

        $container = $this->createPsr11Container($routes);
        $parameters = $this->createParameterBag(['object' => new \stdClass()]);

        (new Router($container, 'foo', [], null, $parameters))
            ->getRouteCollection()->get('foo');
    }

    public function testThrowsExceptionOnNonStringParameterFromASymfonyContainer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The container parameter "object", used in the route configuration value "/%object%", must be a string or numeric, but it is of type "stdClass".');

        $routes = new RouteCollection();
        $routes->add('foo', new Route('/%object%'));

        $container = $this->createSymfonyContainer($routes);
        $parameters = $this->createParameterBag(['object' => new \stdClass()]);

        (new Router($container, 'foo', [], null, $parameters))
            ->getRouteCollection()->get('foo');
    }

    private function createParameterBag(array $params = []): MockObject&ContainerInterface
    {
        /** @var MockObject&ContainerInterface $bag */
        $bag = $this->createMock(ContainerInterface::class);
        $bag->method('get')
            ->willReturnCallback(static fn ($key): mixed => $params[$key] ?? null);

        return $bag;
    }

    private function createPsr11Container(RouteCollection $routes): MockObject&ContainerInterface
    {
        /** @var MockObject&LoaderInterface $loader */
        $loader = $this->createMock(LoaderInterface::class);

        $loader->method('load')
            ->willReturn($routes);

        /** @var MockObject&ContainerInterface $container */
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('get')
            ->willReturn($loader);

        return $container;
    }

    private function createSymfonyContainer(RouteCollection $routes): Container
    {
        /** @var MockObject&LoaderInterface $loader */
        $loader = $this->createMock(LoaderInterface::class);

        $loader->method('load')
            ->willReturn($routes);

        /** @var MockObject&Container $container */
        $container = $this->getMockBuilder(Container::class)->onlyMethods(['get'])->getMock();

        $container->expects($this->once())
            ->method('get')
            ->willReturn($loader)
        ;

        return $container;
    }
}
