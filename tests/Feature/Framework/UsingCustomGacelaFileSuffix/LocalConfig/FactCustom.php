<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomGacelaFileSuffix\LocalConfig;

use Gacela\Framework\AbstractFactory;

/**
 * @method ConfCustom getConfig()
 */
final class FactCustom extends AbstractFactory
{
    public function getArrayConfigAndProvidedDependency(): array
    {
        return [
            'config-key' => $this->getConfig()->getConfigValue(),
            'provided-dependency' => $this->getProvidedDependency('provided-dependency'),
        ];
    }
}
