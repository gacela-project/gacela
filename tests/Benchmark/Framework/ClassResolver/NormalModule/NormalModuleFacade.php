<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule;

use Gacela\Framework\AbstractFacade;

/**
 * @method NormalModuleFactory getFactory()
 */
final class NormalModuleFacade extends AbstractFacade
{
    public function getConfigValues(): array
    {
        return $this->getFactory()
            ->createDomainClass()
            ->getConfigValues();
    }

    public function getValueFromDependencyProvider(): string
    {
        return $this->getFactory()
            ->createDomainClass()
            ->getValueFromDependencyProvider();
    }
}
