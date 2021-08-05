<?php

declare(strict_types=1);

use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Factory as LocalConfigFactory;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure\CustomCompanyGenerator;

return [
    'dependencies' => [
        LocalConfigFactory::class => [
            CustomCompanyGenerator::class,
            'random-string',
            true,
            100,
            1.23,
            fn () => 2,
        ],
    ],
];
