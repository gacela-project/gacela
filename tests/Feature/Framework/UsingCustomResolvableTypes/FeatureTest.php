<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomResolvableTypes;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_load_multiple_config_files(): void
    {
        $facade = new LocalConfig\FacaCustom();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $facade->doSomething()
        );
    }
}
