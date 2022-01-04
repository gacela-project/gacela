<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\NormalModule;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Benchmark\Framework\ClassResolver\NormalModule\Domain\DomainClass;

/**
 * @method NormalModuleConfig getConfig()
 */
final class NormalModuleFactory extends AbstractFactory
{
    public function createDomainClass(): DomainClass
    {
        $configValues = $this->getConfig()->getValues();

        /** @var string $valueFromDependencyProvider */
        $valueFromDependencyProvider = $this->getProvidedDependency('key');

        return new DomainClass($configValues, $valueFromDependencyProvider);
    }
}
