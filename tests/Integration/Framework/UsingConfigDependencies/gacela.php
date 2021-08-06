<?php

declare(strict_types=1);

use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure\CustomCompanyGenerator;

return [
    'dependencies' => [
        GreeterGeneratorInterface::class => CustomCompanyGenerator::class,
    ],
];
