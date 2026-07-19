<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared\Clock;

final class SharedKernelFactory
{
    public function createIt(): Clock
    {
        return new Clock();
    }
}
