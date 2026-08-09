<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate;

trait LogicTrait
{
    public function fromTheTrait(): int
    {
        return 1 + 1;
    }
}
