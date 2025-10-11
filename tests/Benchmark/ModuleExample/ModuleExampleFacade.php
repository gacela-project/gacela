<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ModuleExample;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<ModuleExampleFactory>
 */
final class ModuleExampleFacade extends AbstractFacade
{
    public function getConfigValues(): array
    {
        return $this->getFactory()
            ->createDomainClass()
            ->getConfigValues();
    }

    public function getValueFromAbstractProvider(): string
    {
        return $this->getFactory()
            ->createDomainClass()
            ->getValueFromAbstractProvider();
    }
}
