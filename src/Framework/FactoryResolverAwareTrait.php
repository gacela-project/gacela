<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use RuntimeException;

/**
 * @method static AbstractFactory factory()
 */
trait FactoryResolverAwareTrait
{
    private ?AbstractFactory $factory = null;

    public static function __callStatic(string $name, array $arguments): AbstractFactory
    {
        if ($name === 'factory') {
            return (new static())->getFactory();
        }

        throw new RuntimeException('Unknown method: ' . $name);
    }

    protected function getFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = (new FactoryResolver())->resolve($this);
        }

        return $this->factory;
    }
}
