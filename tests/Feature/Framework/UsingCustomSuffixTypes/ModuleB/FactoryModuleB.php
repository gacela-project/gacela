<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleB;

use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<ConfigModuleB>
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
