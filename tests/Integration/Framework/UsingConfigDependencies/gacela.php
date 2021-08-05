<?php

declare(strict_types=1);

use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Factory as LocalConfigFactory;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure\CustomCompanyGenerator;

return [
    'dependencies' => [
        LocalConfigFactory::class => [
            CustomCompanyGenerator::class,
        ],
    ],
];
