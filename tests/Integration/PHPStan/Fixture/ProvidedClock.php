<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\Fixture;

interface ProvidedClock
{
    public function now(): string;
}
