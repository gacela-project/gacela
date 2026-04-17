<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate;

final class NotAFacade
{
    public function compute(int $x): int
    {
        $value = $x;

        return $value + 1;
    }
}
