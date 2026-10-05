<?php declare(strict_types=1);

namespace BabDev\WebSocketBundle\DependencyInjection\Config;

use Symfony\Component\Config\Definition\Builder\FloatNodeDefinition;

/**
 * @internal
 */
final class NullableFloatNodeDefinition extends FloatNodeDefinition
{
    private bool $positive = false;

    /**
     * Requires the value to be greater than 0.
     *
     * @return $this
     */
    public function positive(): self
    {
        $this->positive = true;

        return $this;
    }

    protected function instantiateNode(): NullableFloatNode
    {
        return new NullableFloatNode($this->name, $this->parent, $this->min, $this->max, $this->pathSeparator, $this->positive);
    }
}
