<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Fixtures\CustomInterface;

return static fn (GacelaConfig $config): GacelaConfig => $config
    ->addAppConfig('config/from-gacela-file.php')
    ->addBinding(CustomInterface::class, $config->getExternalService('CustomClassFromExternalService'))
    ->addSuffixTypeFacade('FacadeFromGacelaFile')
    ->addSuffixTypeFactory('FactoryFromGacelaFile')
    ->addSuffixTypeConfig('ConfigFromGacelaFile')
    ->addSuffixTypeDependencyProvider('DependencyProviderFromGacelaFile');
