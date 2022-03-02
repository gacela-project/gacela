<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes;

use Gacela\Framework\AbstractConfigGacela;

return static fn () => new class () extends AbstractConfigGacela {
    public function overrideResolvableTypes(): array
    {
        return [
            'Factory' => ['FactoryModuleA', 'FactoryModuleB'],
            'Config' => ['ConfModuleA', 'ConfModuleB'],
            'DependencyProvider' => ['DepProModuleA', 'DepProModuleB'],
        ];
    }
};
