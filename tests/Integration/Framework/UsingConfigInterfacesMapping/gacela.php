<?php

declare(strict_types=1);

use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Infrastructure\CustomCompanyGenerator;

return [
    'interfaces-mapping' => [
        GreeterGeneratorInterface::class => CustomCompanyGenerator::class,
    ],
];
