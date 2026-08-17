<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugEvents\Fixture;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * An event of the "application" this command is run against: the fixture
 * directory beside this test is its app root, so `debug:events` finds this the
 * way it finds a project's own.
 */
final class StockRunLowEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $sku,
    ) {
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function toString(): string
    {
        return sprintf('Stock run low: %s', $this->sku);
    }
}
