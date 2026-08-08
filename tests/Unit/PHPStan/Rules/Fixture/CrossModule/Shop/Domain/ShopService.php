<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain;

final class ShopService
{
    public function run(): string
    {
        return 'shop';
    }
}
