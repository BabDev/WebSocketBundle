<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\Routing\Loader;

use BabDev\WebSocketBundle\Attribute\AsMessageHandler;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

if (class_exists(AttributeClassLoader::class)) {
    /**
     * @internal Conditional compatibility class for Symfony 6.4 and later
     */
    abstract class CompatClassLoader extends AttributeClassLoader {}
} else {
    /**
     * @internal Conditional compatibility class for Symfony 6.3 and earlier
     */
    abstract class CompatClassLoader extends AnnotationClassLoader {
        public function __construct(?string $env = null)
        {
            parent::__construct(null, $env);
        }
    }
}

/**
 * The attribute loader loads routing information from PHP classes having the {@see AsMessageHandler} attribute.
 */
final class AttributeLoader extends CompatClassLoader
{
    public function __construct(?string $env = null)
    {
        parent::__construct($env);
    }

    /**
     * Loads from attributes from a class.
     *
     * @throws \InvalidArgumentException When the route can't be parsed
     */
    public function load(mixed $class, ?string $type = null): RouteCollection
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        $class = new \ReflectionClass($class);

        if ($class->isAbstract()) {
            throw new \InvalidArgumentException(sprintf('Attributes from class "%s" cannot be read as it is abstract.', $class->getName()));
        }

        $collection = new RouteCollection();
        $collection->addResource(new FileResource($class->getFileName()));

        /** @var \ReflectionAttribute<AsMessageHandler>|null $attribute */
        $attribute = $class->getAttributes(AsMessageHandler::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        if (!$attribute instanceof \ReflectionAttribute) {
            return $collection;
        }

        /** @var AsMessageHandler $attrib */
        $attrib = $attribute->newInstance();

        if ($attrib->getEnv() && $attrib->getEnv() !== $this->env) {
            return $collection;
        }

        $requirements = $attrib->getRequirements();

        foreach ($requirements as $placeholder => $requirement) {
            if (\is_int($placeholder)) {
                throw new \InvalidArgumentException(sprintf('A placeholder name must be a string (%d given). Did you forget to specify the placeholder key for the requirement "%s" of the route in "%s"?', $placeholder, $requirement, $class->getName()));
            }
        }

        $name = $attrib->getName() ?? $this->getDefaultRouteName($class);
        $defaults = $attrib->getDefaults();
        $options = $attrib->getOptions();
        $schemes = $attrib->getSchemes();
        $methods = $attrib->getMethods();

        $host = $attrib->getHost() ?? '';
        $condition = $attrib->getCondition() ?? '';
        $priority = $attrib->getPriority() ?? 0;

        $path = $attrib->getLocalizedPaths() ?: $attrib->getPath();
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

            if (0 !== $locale) {
                $route->setDefault('_locale', $locale);
                $route->setRequirement('_locale', preg_quote($locale));
                $route->setDefault('_canonical_route', $name);
                $collection->add($name.'.'.$locale, $route, $priority);
            } else {
                $collection->add($name, $route, $priority);
            }
        }

        return $collection;
    }

    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot): void
    {
        // Method is purposefully unused, but is required by the parent class
    }

    /**
     * Build the default route name for a message handler.
     */
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
