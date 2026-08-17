<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax;

interface TaxRateInterface
{
    public function basisPoints(): int;
}
