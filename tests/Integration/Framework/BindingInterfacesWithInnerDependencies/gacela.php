<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Infrastructure\CorrectCompanyGenerator;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Infrastructure\IncorrectCompanyGenerator;

/**
 * This integration-test does two things:
 *
 * - 1: Check the "globalService" variable was properly defined in the 'Gacela::bootstrap()' with the key `isWorking?`.
 *
 * - 2: Let Gacela resolve in the factory the mapping from `GreeterGeneratorInterface` to `CorrectCompanyGenerator`
 *      AND auto-resolve the class `CustomNameGenerator` from the `CorrectCompanyGenerator` constructor.
 */
return static function (array $globalServices = []): AbstractConfigGacela {
    return new class($globalServices) extends AbstractConfigGacela {
        public function mappingInterfaces(): array
        {
            $interfaces = [GreeterGeneratorInterface::class => IncorrectCompanyGenerator::class];

            if ('yes!' === $this->getGlobalService('isWorking?')) {
                $interfaces[GreeterGeneratorInterface::class] = CorrectCompanyGenerator::class;
            }

            return $interfaces;
        }
    };
};
