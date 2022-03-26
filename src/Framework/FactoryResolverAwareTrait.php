<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

trait FactoryResolverAwareTrait
{
    private ?AbstractFactory $factory = null;

    protected function getFactory(): AbstractFactory
    {
        if ($this->factory === null) {
            $this->factory = (new FactoryResolver())->resolve($this);
        }

        return $this->factory;
    }
}
