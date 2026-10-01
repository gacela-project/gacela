<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RequestState\Cart;

final class CurrentUser
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
