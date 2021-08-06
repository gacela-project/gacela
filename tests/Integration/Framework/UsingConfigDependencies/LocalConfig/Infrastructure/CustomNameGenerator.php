<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure;

use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\NumberGeneratorInterface;

final class CustomNameGenerator implements NumberGeneratorInterface
{
    public function getNames(): string
    {
        return 'Chemaclass & Jesus';
    }
}
