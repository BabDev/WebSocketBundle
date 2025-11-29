<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Routing\Loader;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The attribute loader loads routing information from PHP classes having the {@see AsMessageHandler} attribute.
 */
final class AttributeLoader extends AttributeClassLoader
{
    /**
     * @var class-string
     */
    private string $routeAttributeClass = AsMessageHandler::class;

    public function __construct(?string $env = null)
    {
        parent::__construct($env);

        /** @phpstan-ignore function.alreadyNarrowedType */
        if (method_exists(AttributeClassLoader::class, 'setRouteAttributeClass')) {
            $this->setRouteAttributeClass(AsMessageHandler::class);
        } else {
            $this->setRouteAnnotationClass(AsMessageHandler::class);
        }
    }

    /**
     * @param class-string $class
     */
    #[\Deprecated(message: 'use setRouteAttributeClass() instead', since: 'babdev/websocket-server 0.1')]
    public function setRouteAnnotationClass(string $class): void
    {
        $this->routeAttributeClass = $class;

        if (method_exists(AttributeClassLoader::class, 'setRouteAnnotationClass')) {
            parent::setRouteAnnotationClass($class); // @phpstan-ignore staticMethod.notFound
        }
    }

    /**
     * @param class-string $class
     */
    #[\Override]
    public function setRouteAttributeClass(string $class): void
    {
        $this->routeAttributeClass = $class;

        parent::setRouteAttributeClass($class);
    }

    /**
     * Loads from attributes from a class.
     *
     * @throws \InvalidArgumentException When the route can't be parsed
     */
    #[\Override]
    public function load(mixed $class, ?string $type = null): RouteCollection
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist.', $class));
        }

        /** @var \ReflectionClass<AsMessageHandler> $class */
        $class = new \ReflectionClass($class);

        if ($class->isAbstract()) {
            throw new \InvalidArgumentException(\sprintf('Attributes from class "%s" cannot be read as it is abstract.', $class->getName()));
        }

        $collection = new RouteCollection();
        $collection->addResource(new FileResource($class->getFileName()));

        /** @var \ReflectionAttribute<AsMessageHandler>|null $attribute */
        $attribute = $class->getAttributes($this->routeAttributeClass, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        if (!$attribute instanceof \ReflectionAttribute) {
            return $collection;
        }

        /** @var AsMessageHandler $attr */
        $attr = $attribute->newInstance();

        if ($attr->envs && !\in_array($this->env, $attr->envs, true)) {
            return $collection;
        }

        $requirements = $attr->requirements;

        foreach ($requirements as $placeholder => $requirement) {
            if (\is_int($placeholder)) {
                throw new \InvalidArgumentException(\sprintf('A placeholder name must be a string (%d given). Did you forget to specify the placeholder key for the requirement "%s" of the route in "%s"?', $placeholder, $requirement, $class->getName()));
            }
        }

        $name = $attr->name ?? $this->getDefaultRouteName($class);
        $defaults = $attr->defaults;
        $options = $attr->options;
        $schemes = $attr->schemes;
        $methods = $attr->methods;

        $host = $attr->host ?? '';
        $condition = $attr->condition ?? '';
        $priority = $attr->priority ?? 0;

        $path = $attr->path;
        $paths = [];

        if (\is_array($path)) {
            foreach ($path as $locale => $localePath) {
                $paths[$locale] = $localePath;
            }
        } else {
            $paths[] = $path;
        }

        foreach ($paths as $locale => $path) {
            $route = $this->createRoute($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
            $route->setDefault('_controller', $class->getName());

            if (0 !== $locale) {
                $route->setDefault('_locale', $locale);
                $route->setRequirement('_locale', preg_quote((string) $locale));
                $route->setDefault('_canonical_route', $name);
                $collection->add($name.'.'.$locale, $route, $priority);
            } else {
                $collection->add($name, $route, $priority);
            }
        }

        return $collection;
    }

    /**
     * @param \ReflectionClass<AsMessageHandler> $class
     */
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
    {
        // Method is purposefully unused, but is required by the parent class
    }

    /**
     * Build the default route name for a message handler.
     *
     * @param \ReflectionClass<AsMessageHandler> $class
     */
    #[\Override]
    protected function getDefaultRouteName(\ReflectionClass $class, ?\ReflectionMethod $method = null): string
    {
        $name = str_replace('\\', '_', $class->name);
        $name = \function_exists('mb_strtolower') && preg_match('//u', $name) ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $name = preg_replace('/(bundle|messagehandler)_/', '_', $name);

        if ($this->defaultRouteIndex > 0) {
            $name .= '_'.$this->defaultRouteIndex;
        }

        ++$this->defaultRouteIndex;

        return str_replace('__', '_', $name);
    }
}
