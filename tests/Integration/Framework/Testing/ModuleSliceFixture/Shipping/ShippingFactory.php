<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shipping;

use Gacela\Framework\AbstractFactory;

final class ShippingFactory extends AbstractFactory
{
    public function costOf(string $article): int
    {
        return $article === '' ? 0 : 500;
    }
}
