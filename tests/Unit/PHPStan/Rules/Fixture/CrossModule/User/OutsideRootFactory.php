<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use DateTimeImmutable;

final class OutsideRootFactory
{
    public function createIt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
