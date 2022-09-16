<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes;

use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config
        // ModuleA
        ->addSuffixTypeFacade('FacaModuleA')
        ->addSuffixTypeFactory('FactModuleA')
        ->addSuffixTypeConfig('ConfModuleA')
        ->addSuffixTypeDependencyProvider('DepProModuleA')
        // ModuleB
        ->addSuffixTypeFacade('FacadeModuleB')
        ->addSuffixTypeFactory('FactoryModuleB')
        ->addSuffixTypeConfig('ConfigModuleB')
        ->addSuffixTypeDependencyProvider('DependencyProviderModuleB');
};
