<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

/**
 * Within the same gacela bootstrap, it recognizes different suffixes for its gacela files.
 */
final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
        });
    }

    public function test_load_module_a(): void
    {
        $commandA = new ModuleA\CommandModuleA();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $commandA->execute(),
        );
    }

    public function test_load_module_b(): void
    {
        $commandB = new ModuleB\CommandModuleB();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $commandB->execute(),
        );
    }

    public function test_load_module_c(): void
    {
        $commandC = new ModuleC\CommandModuleC();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $commandC->execute(),
        );
    }
}
