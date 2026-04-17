<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Other;

final class NoDepthRefFactory
{
    public function createIt(): Other
    {
        return new Other();
    }
}
