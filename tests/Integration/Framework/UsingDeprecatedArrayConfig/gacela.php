<?php

declare(strict_types=1);

use GacelaTest\Integration\Framework\UsingDeprecatedArrayConfig\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingDeprecatedArrayConfig\LocalConfig\Infrastructure\CustomCompanyGenerator;

return [
    'mapping-interfaces' => [
        GreeterGeneratorInterface::class => CustomCompanyGenerator::class,
    ],
];
