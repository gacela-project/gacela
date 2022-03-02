<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes\ModuleA;

use Gacela\Framework\AbstractFactory;

/**
 * @method ConfModuleA getConfig()
 */
final class FactoryModuleA extends AbstractFactory
{
    public function getArrayConfigAndProvidedDependency(): array
    {
        return [
            'config-key' => $this->getConfig()->getConfigValue(),
            'provided-dependency' => $this->getProvidedDependency('provided-dependency'),
        ];
    }
}
