<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\Module\Domain\Greeter\IncorrectCompanyGenerator;
use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\Module\Domain\GreeterGeneratorInterface;

return static function (GacelaConfig $config): void {
    $config->addBinding(GreeterGeneratorInterface::class, IncorrectCompanyGenerator::class);

    // Overriding the `GreeterGeneratorInterface` with the proper external service.
    // Check the FeatureTest class to see how the external service with key `greeterGenerator` is defined.
    $config->addBinding(GreeterGeneratorInterface::class, $config->getExternalService('greeterGenerator'));
};
