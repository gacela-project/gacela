<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\Domain;

final class PriceList
{
    /**
     * @param array<string, int> $pricesByArticle
     */
    public function __construct(
        private readonly array $pricesByArticle,
    ) {
    }

    public function priceOf(string $article): int
    {
        return $this->pricesByArticle[$article] ?? 0;
    }
}
