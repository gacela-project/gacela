<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface;

interface NotAFacadeInterface
{
    public function known(): string;

    public function alsoKnown(): int;
}
