<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Config;

use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\NodeInterface;

/**
 * A float node which also accepts null, for options which are disabled with a null value.
 *
 * @internal
 */
final class NullableFloatNode extends FloatNode
{
    public function __construct(
        ?string $name,
        ?NodeInterface $parent = null,
        int|float|null $min = null,
        int|float|null $max = null,
        string $pathSeparator = BaseNode::DEFAULT_PATH_SEPARATOR,
        private readonly bool $positive = false,
    ) {
        parent::__construct($name, $parent, $min, $max, $pathSeparator);
    }

    protected function validateType(mixed $value): void
    {
        if (null === $value) {
            return;
        }

        parent::validateType($value);
    }

    protected function finalizeValue(mixed $value): mixed
    {
        if (null === $value) {
            return null;
        }

        $value = parent::finalizeValue($value);

        // Like the range checks, this is skipped for environment variables as their value is not known yet
        if ($this->positive && !$this->isHandlingPlaceholder() && (\is_int($value) || \is_float($value)) && $value <= 0) {
            $exception = new InvalidConfigurationException(\sprintf('The value %s is too small for path "%s". Should be greater than 0', $value, $this->getPath()));
            $exception->setPath($this->getPath());

            throw $exception;
        }

        return $value;
    }
}
