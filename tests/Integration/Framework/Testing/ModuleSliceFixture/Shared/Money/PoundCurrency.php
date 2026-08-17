<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Money;

/**
 * What a slice puts in place of {@see EuroCurrency}.
 */
final class PoundCurrency implements CurrencyInterface
{
    public function code(): string
    {
        return 'GBP';
    }
}
