<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RequestState\Cart;

final class Cart
{
    /** @var list<string> */
    private array $items = [];

    public function add(string $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return list<string>
     */
    public function items(): array
    {
        return $this->items;
    }
}
