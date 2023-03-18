<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

trait FactoryResolverAwareTrait
{
    private ?AbstractFactory $factory = null;

    /**
     * Syntax sugar to access the factory from static methods.
     */
    protected static function factory(): AbstractFactory
    {
        return (new static())->getFactory();
    }

    protected function getFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = (new FactoryResolver())->resolve($this);
        }

        return $this->factory;
    }
}
