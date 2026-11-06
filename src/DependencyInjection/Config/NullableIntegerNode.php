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
    /**
     * Reports that null is accepted, as the default value does not tell; Symfony 8.2 uses this to describe the node in the generated JSON schema.
     */
    public function isNullable(): bool
    {
        return true;
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

        return parent::finalizeValue($value);
    }
}
