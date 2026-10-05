<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Config;

use Symfony\Component\Config\Definition\IntegerNode;

/**
 * An integer node which also accepts null, for options which are disabled with a null value.
 *
 * @internal
 */
final class NullableIntegerNode extends IntegerNode
{
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

        return parent::finalizeValue($value);
    }
}
