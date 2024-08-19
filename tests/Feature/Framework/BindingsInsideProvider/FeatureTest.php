<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_mapping_interfaces_from_bindings(): void
    {
        self::assertSame(
            'Hello Gacela! Team: Chemaclass & Jesus',
            (new Module\Facade())->generateCompanyAndName(),
        );
    }
}
