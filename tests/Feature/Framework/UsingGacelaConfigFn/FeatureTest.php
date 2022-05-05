<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingGacelaConfigFn;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_config_php_files(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config' => 1,
                'override' => 2,
                'local' => 3,
                'override_from_local' => 4,
            ],
            $facade->doSomething()
        );
    }
}
