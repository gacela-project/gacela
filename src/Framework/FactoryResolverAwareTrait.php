<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use RuntimeException;

/**
 * @method AbstractFactory factory()
 * @method AbstractFactory getFactory()
 */
trait FactoryResolverAwareTrait
{
    private ?AbstractFactory $factory = null;

    /**
     * Syntax sugar to access the factory from static methods.
     */
    public static function __callStatic(string $name, array $arguments): AbstractFactory
    {
        if ($name === 'factory' || $name === 'getFactory') {
            return (new static())->doGetFactory();
        }

        throw new RuntimeException("Method unknown: {$name}");
    }

    public function __call(string $name, array $arguments): AbstractFactory
    {
        if ($name === 'factory' || $name === 'getFactory') {
            return $this->doGetFactory();
        }

        throw new RuntimeException("Method unknown: {$name}");
    }

    private function doGetFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = (new FactoryResolver())->resolve($this);
        }

        return $this->factory;
    }
}
