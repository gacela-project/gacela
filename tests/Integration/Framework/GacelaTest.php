<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class GacelaTest extends TestCase
{
    public function test_null_get_cache_dir(): void
    {
        self::assertNull(Gacela::rootDir());
    }

    /**
     * @depends test_null_get_cache_dir
     */
    public function test_get_cache_dir(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        self::assertEquals(__DIR__, Gacela::rootDir());
    }
}
