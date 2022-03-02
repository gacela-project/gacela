<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes\ModuleB;

use Gacela\Framework\AbstractFactory;

/**
 * @method ConfModuleB getConfig()
 */
final class FactoryModuleB extends AbstractFactory
{
    public function getArrayConfigAndProvidedDependency(): array
    {
        return [
            'config-key' => $this->getConfig()->getConfigValue(),
            'provided-dependency' => $this->getProvidedDependency('provided-dependency'),
        ];
    }
}
