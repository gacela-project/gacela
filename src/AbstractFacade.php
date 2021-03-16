<?php

declare(strict_types=1);

namespace Gacela;

use Gacela\ClassResolver\Factory\FactoryResolver;

abstract class AbstractFacade
{
    private ?AbstractFactory $factory = null;

    protected function getFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->resolveFactory();
        }

        return $this->factory;
    }

    private function resolveFactory(): AbstractFactory
    {
        return $this->getFactoryResolver()->resolve($this);
    }

    private function getFactoryResolver(): FactoryResolver
    {
        return new FactoryResolver();
    }
}
