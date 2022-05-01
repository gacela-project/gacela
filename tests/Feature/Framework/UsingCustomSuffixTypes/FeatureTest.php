<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Gacela;
use Gacela\Framework\Setup\SetupGacela;
use PHPUnit\Framework\TestCase;

/**
 * Within the same gacela bootstrap, it recognizes different suffixes for its gacela files.
 */
final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        $setup = (new SetupGacela())
            ->setConfig(static function (ConfigBuilder $configBuilder): void {
                $configBuilder->add('config/*.php', 'config/local.php');
            });

        Gacela::bootstrap(__DIR__, $setup);
    }

    public function test_load_module_a(): void
    {
        $commandA = new ModuleA\CommandModuleA();

        self::assertSame(
            [
                'config-key' => 'config-value',
                'provided-dependency' => 'dependency-value',
            ],
            $commandA->execute()
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
            $commandB->execute()
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
            $commandC->execute()
        );
    }
}
