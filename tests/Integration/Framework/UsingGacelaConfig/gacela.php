<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use GacelaTest\Integration\Framework\UsingGacelaConfig\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingGacelaConfig\LocalConfig\Infrastructure\CustomCompanyGenerator;

return static function (array $globalServices = []): AbstractConfigGacela {
    return new class($globalServices) extends AbstractConfigGacela {
        public function config(): array
        {
            return [
                'type' => 'env',
                'path' => '.env*',
                'path_local' => '.env',
            ];
        }

        public function mappingInterfaces(): array
        {
            return [
                GreeterGeneratorInterface::class => CustomCompanyGenerator::class,
            ];
        }
    };
};
