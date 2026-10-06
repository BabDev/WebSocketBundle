<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Tests\Routing\Loader;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;
use BabDev\WebSocketBundle\Routing\Loader\AttributeLoader;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\AbstractMessageHandler;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\DevelopmentMessageHandler;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\InvalidRequirementMessageHandler;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\LocalizedMessageHandler;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\MissingPathMessageHandler;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\NamedMessageHandler;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\NotAMessageHandler;
use BabDev\WebSocketBundle\Tests\Fixtures\Routing\SimpleMessageHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class AttributeLoaderTest extends TestCase
{
    public function testARouteIsLoadedForAMessageHandler(): void
    {
        $routes = new AttributeLoader()->load(SimpleMessageHandler::class);

        self::assertCount(1, $routes);

        $route = $routes->get('babdev_websocket_tests_fixtures_routing_simplemessagehandler');

        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/echo', $route->getPath());
        self::assertSame(SimpleMessageHandler::class, $route->getDefault('_controller'), 'The message handler is found by its class name.');
    }

    public function testTheRouteConfigurationFromTheAttributeIsUsed(): void
    {
        $routes = new AttributeLoader()->load(NamedMessageHandler::class);

        $route = $routes->get('chat_room');

        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/chat/{room}', $route->getPath());
        self::assertSame('\d+', $route->getRequirement('room'));

        // A route added earlier with the default priority is matched after the higher priority route
        $collection = new RouteCollection();
        $collection->add('fallback', new Route('/chat/{room}'));
        $collection->addCollection($routes);

        self::assertArraysAreIdentical(['chat_room', 'fallback'], array_keys($collection->all()));
    }

    public function testARouteIsLoadedForEachLocale(): void
    {
        $routes = new AttributeLoader()->load(LocalizedMessageHandler::class);

        self::assertCount(2, $routes);

        foreach (['en' => '/hello', 'fr' => '/bonjour'] as $locale => $path) {
            $route = $routes->get("greeting.{$locale}");

            self::assertInstanceOf(Route::class, $route);
            self::assertSame($path, $route->getPath());
            self::assertSame($locale, $route->getDefault('_locale'));
            self::assertSame($locale, $route->getRequirement('_locale'));
            self::assertSame('greeting', $route->getDefault('_canonical_route'));
        }
    }

    public function testARouteIsOnlyLoadedInItsEnvironment(): void
    {
        self::assertCount(1, new AttributeLoader('dev')->load(DevelopmentMessageHandler::class));
        self::assertCount(0, new AttributeLoader('prod')->load(DevelopmentMessageHandler::class));
    }

    public function testNoRouteIsLoadedForAClassWithoutTheAttribute(): void
    {
        self::assertCount(0, new AttributeLoader()->load(NotAMessageHandler::class));
    }

    public function testAnAbstractClassCannotBeLoaded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(\sprintf('Attributes from class "%s" cannot be read as it is abstract.', AbstractMessageHandler::class));

        new AttributeLoader()->load(AbstractMessageHandler::class);
    }

    public function testAClassWhichDoesNotExistCannotBeLoaded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Class "App\WebSocket\MissingMessageHandler" does not exist.');

        new AttributeLoader()->load('App\WebSocket\MissingMessageHandler');
    }

    public function testARequirementWithoutAPlaceholderNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(\sprintf('A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "\d+" of the route in "%s"?', InvalidRequirementMessageHandler::class));

        new AttributeLoader()->load(InvalidRequirementMessageHandler::class);
    }

    public function testAMessageHandlerWithoutAPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(\sprintf('The "%s" attribute on class "%s" must define a path.', AsMessageHandler::class, MissingPathMessageHandler::class));

        new AttributeLoader()->load(MissingPathMessageHandler::class);
    }
}
