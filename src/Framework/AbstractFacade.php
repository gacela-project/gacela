<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

abstract class AbstractFacade
{
    private ?AbstractFactory $factory = null;

    protected function getFactory(): AbstractFactory
    {
        if (null === $this->factory) {
            $this->factory = $this->resolveFactory();
        }

        return $this->factory;
    }

    private function resolveFactory(): AbstractFactory
    {
        return $this->createFactoryResolver()->resolve($this);
    }

    private function createFactoryResolver(): FactoryResolver
    {
        return new FactoryResolver();
    }
}
