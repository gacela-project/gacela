<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TypedConfigAccess;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\TypedConfigAccess\Module\Facade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->addAppConfigKeyValues([
                'name' => 'checkout',
                'retries' => 3,
                'ratio' => 1.5,
                'enabled' => true,
                'tags' => ['a', 'b'],
            ]);
        });
    }

    public function test_module_config_reads_typed_values(): void
    {
        self::assertSame(
            [
                'name' => 'checkout',
                'retries' => 3,
                'ratio' => 1.5,
                'enabled' => true,
                'tags' => ['a', 'b'],
                'timeout' => 30,
            ],
            (new Facade())->readTypedValues(),
        );
    }
}
