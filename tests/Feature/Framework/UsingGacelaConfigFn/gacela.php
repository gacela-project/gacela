<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\UsingGacelaConfigFn\LocalConfig\Domain\Greeter\IncorrectCompanyGenerator;
use GacelaTest\Feature\Framework\UsingGacelaConfigFn\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Fixtures\CustomInterface;

return static function (GacelaConfig $config): void {
    $config
        // adding app configuration
        ->addAppConfig('config/*.php', 'config/local.php')

        // adding suffix types
        ->addSuffixTypeFacade('FacadeFromBootstrap')
        ->addSuffixTypeFactory('FactoryFromBootstrap')
        ->addSuffixTypeConfig('ConfigFromBootstrap')
        ->addSuffixTypeDependencyProvider('DependencyProviderFromBootstrap')

        // Mapping interfaces
        ->mapInterface(GreeterGeneratorInterface::class, IncorrectCompanyGenerator::class)
        ->mapInterface(CustomInterface::class, $config->getExternalService('CustomClassFromExternalService'));
};
