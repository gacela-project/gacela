<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Fixtures\CustomInterface;

return static fn (GacelaConfig $config) => $config
    ->addAppConfig('config/from-gacela-file.php')
    ->addMappingInterface(CustomInterface::class, $config->getExternalService('CustomClassFromExternalService'))
    ->addSuffixTypeFacade('FacadeFromGacelaFile')
    ->addSuffixTypeFactory('FactoryFromGacelaFile')
    ->addSuffixTypeConfig('ConfigFromGacelaFile')
    ->addSuffixTypeDependencyProvider('DependencyProviderFromGacelaFile');
