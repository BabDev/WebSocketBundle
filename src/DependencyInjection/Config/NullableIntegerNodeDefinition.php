<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Config;

use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;

/**
 * @internal
 */
final class NullableIntegerNodeDefinition extends IntegerNodeDefinition
{
    protected function instantiateNode(): NullableIntegerNode
    {
        return new NullableIntegerNode($this->name, $this->parent, $this->min, $this->max, $this->pathSeparator);
    }
}
