<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Setup\SetupGacela;

return (new SetupGacela())
    ->setSuffixTypesFn(
        static function (SuffixTypesBuilder $suffixTypesBuilder): void {
            $suffixTypesBuilder
                ->addFacade('FacaModuleA')
                ->addFactory('FactModuleA')
                ->addConfig('ConfModuleA')
                ->addDependencyProvider('DepProModuleA')

                ->addFacade('FacadeModuleB')
                ->addFactory('FactoryModuleB')
                ->addConfig('ConfigModuleB')
                ->addDependencyProvider('DependencyProviderModuleB');
        }
    );
