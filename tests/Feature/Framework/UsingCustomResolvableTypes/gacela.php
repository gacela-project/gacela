<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigArgs\ResolvableTypesConfig;

return static fn () => new class () extends AbstractConfigGacela {
    public function overrideResolvableTypes(ResolvableTypesConfig $resolvableTypesConfig): void
    {
        $resolvableTypesConfig
            ->addFactory('FactoryModuleA')
            ->addFactory('FactoryModuleB')
            ->addConfig('ConfModuleA')
            ->addConfig('ConfModuleB')
            ->addDependencyProvider('DepProModuleA')
            ->addDependencyProvider('DepProModuleB');
    }
};
