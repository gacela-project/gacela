<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

/**
 * Within the same gacela bootstrap, it recognizes different suffixes for its gacela files.
 */
final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_load_module_a(): void
    {
        $facade = new ModuleA\FacadeModuleA();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $facade->doSomething()
        );
    }

    public function test_load_module_b(): void
    {
        $facade = new ModuleB\FacadeModuleB();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $facade->doSomething()
        );
    }

    public function test_load_module_c(): void
    {
        $facade = new ModuleC\Facade();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $facade->doSomething()
        );
    }
}
