<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\Greeter\IncorrectCompanyGenerator;
use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterGeneratorInterface;

return static function (GacelaConfig $config): void {
    $config->addMappingInterface(GreeterGeneratorInterface::class, IncorrectCompanyGenerator::class);

    // Overriding the `GreeterGeneratorInterface` with the proper external service.
    // Check the FeatureTest class to see how the external service with key `greeterGenerator` is defined.
    $config->addMappingInterface(GreeterGeneratorInterface::class, $config->getExternalService('greeterGenerator'));
};
