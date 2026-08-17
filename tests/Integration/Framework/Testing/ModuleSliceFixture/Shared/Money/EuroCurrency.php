<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Money;

final class EuroCurrency implements CurrencyInterface
{
    public function code(): string
    {
        return 'EUR';
    }
}
