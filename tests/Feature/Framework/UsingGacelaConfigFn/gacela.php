<?php

declare(strict_types=1);

use Gacela\Framework\Setup\GacelaConfig;
use GacelaTest\Feature\Framework\UsingGacelaConfigFn\LocalConfig\Domain\Greeter\IncorrectCompanyGenerator;
use GacelaTest\Feature\Framework\UsingGacelaConfigFn\LocalConfig\Domain\GreeterGeneratorInterface;

return static function (GacelaConfig $config): void {
    $config
        ->addAppConfig('config/*.php', 'config/local.php')

        ->addSuffixTypeFacade('FacadeFromBootstrap')
        ->addSuffixTypeFactory('FactoryFromBootstrap')
        ->addSuffixTypeConfig('ConfigFromBootstrap')
        ->addSuffixTypeDependencyProvider('DependencyProviderFromBootstrap')

        ->mapInterface(GreeterGeneratorInterface::class, IncorrectCompanyGenerator::class)
    ;
};
