<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\Greeter\CorrectCompanyGenerator;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\Greeter\IncorrectCompanyGenerator;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterGeneratorInterface;

/**
 * This integration-test does two things:
 *
 * - 1: Check the "globalService" variable was properly defined in the 'Gacela::bootstrap()' with the key `isWorking?`.
 *
 * - 2: Let Gacela resolve in the factory the mapping from `GreeterGeneratorInterface` to `CorrectCompanyGenerator`
 *      AND auto-resolve the class `CustomNameGenerator` from the `CorrectCompanyGenerator` constructor.
 */
return static fn () => new class () extends AbstractConfigGacela {
    public function mappingInterfaces(array $globalServices): array
    {
        $interfaces = [GreeterGeneratorInterface::class => IncorrectCompanyGenerator::class];

        if ('yes!' === $globalServices['isWorking?']) {
            $interfaces[GreeterGeneratorInterface::class] = CorrectCompanyGenerator::class;
        }

        return $interfaces;
    }
};
