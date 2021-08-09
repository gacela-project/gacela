<?php

declare(strict_types=1);

//use Gacela\Framework\AbstractConfigGacela;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Infrastructure\CustomCompanyGenerator;

return [
    'mapping-interfaces' => [
        GreeterGeneratorInterface::class => CustomCompanyGenerator::class,
    ],
];

//return static function (array $globalServices = []): AbstractConfigGacela {
//    return new class($globalServices) extends AbstractConfigGacela {
//        public function mappingInterfaces(): array
//        {
//            return [
//                GreeterGeneratorInterface::class => CustomCompanyGenerator::class,
//            ];
//        }
//    };
//};
