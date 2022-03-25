<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Setup\SetupGacela;

return static fn () => (new SetupGacela())
    ->setSuffixTypes(
        static function (SuffixTypesBuilder $suffixTypesBuilder): void {
            $suffixTypesBuilder
                ->addFactory('FactoryModuleA')
                ->addFactory('FactoryModuleB')
                ->addConfig('ConfModuleA')
                ->addConfig('ConfModuleB')
                ->addDependencyProvider('DepProModuleA')
                ->addDependencyProvider('DepProModuleB');
        }
    );
