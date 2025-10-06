<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\ModuleExample;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Benchmark\ModuleExample\Domain\DomainClass;

/**
 * @method ModuleExampleConfig getConfig()
 */
final class ModuleExampleFactory extends AbstractFactory
{
    public function createDomainClass(): DomainClass
    {
        $configValues = $this->getConfig()->getValues();

        /** @var string $valueFromAbstractProvider */
        $valueFromAbstractProvider = $this->getProvidedDependency('key');

        return new DomainClass($configValues, $valueFromAbstractProvider);
    }
}
