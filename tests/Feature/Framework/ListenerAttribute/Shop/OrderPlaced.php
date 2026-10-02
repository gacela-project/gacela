<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Shop;

final class OrderPlaced implements ShopEvent
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
