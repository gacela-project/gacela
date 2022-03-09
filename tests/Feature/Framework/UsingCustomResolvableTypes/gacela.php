<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

return static fn () => new class () extends AbstractConfigGacela {
    public function suffixTypes(SuffixTypesBuilder $suffixTypesBuilder): void
    {
        $suffixTypesBuilder
            ->addFactory('FactoryModuleA')
            ->addFactory('FactoryModuleB')
            ->addConfig('ConfModuleA')
            ->addConfig('ConfModuleB')
            ->addDependencyProvider('DepProModuleA')
            ->addDependencyProvider('DepProModuleB');
    }
};
