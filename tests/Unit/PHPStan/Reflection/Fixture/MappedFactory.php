<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\AbstractFactory;

final class MappedFactory extends AbstractFactory
{
    public function createName(): string
    {
        return 'mapped';
    }
}
