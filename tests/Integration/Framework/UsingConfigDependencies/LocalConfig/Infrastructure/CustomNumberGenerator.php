<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure;

use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\NumberGeneratorInterface;

final class CustomNumberGenerator implements NumberGeneratorInterface
{
    public function getNumber(): int
    {
        return 100;
//        return random_int(0, 100);
    }
}
