<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigAutoloadDependencies\LocalConfig\Infrastructure;

use GacelaTest\Integration\Framework\UsingConfigAutoloadDependencies\LocalConfig\Domain\NumberGeneratorInterface;

final class CustomNameGenerator implements NumberGeneratorInterface
{
    public function getNames(): string
    {
        return 'Chemaclass & Jesus';
    }
}
