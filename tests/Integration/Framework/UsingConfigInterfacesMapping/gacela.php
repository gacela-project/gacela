<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Infrastructure\CustomCompanyGenerator;

return static function (array $globalServices = []): AbstractConfigGacela {
    return new class($globalServices) extends AbstractConfigGacela {
        public function mappingInterfaces(): array
        {
            $globalService = $this->getGlobalService('isWorking?');

            $interfaces = [];
            if ($globalService === 'yes!' || true) {
                $interfaces = [GreeterGeneratorInterface::class => CustomCompanyGenerator::class];
            }

            return $interfaces;
        }
    };
};
