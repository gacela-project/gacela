<?php

declare(strict_types=1);

use Gacela\Framework\Setup\GacelaConfig;
use GacelaTest\Fixtures\CustomInterface;

return static function (GacelaConfig $config): void {
    $config
        ->addAppConfig('config/from-gacela-file.php')
        ->mapInterface(CustomInterface::class, $config->getExternalService('CustomClassFromExternalService'))
        ->addSuffixTypeFacade('FacadeFromGacelaFile')
        ->addSuffixTypeFactory('FactoryFromGacelaFile')
        ->addSuffixTypeConfig('ConfigFromGacelaFile')
        ->addSuffixTypeDependencyProvider('DependencyProviderFromGacelaFile');
};
