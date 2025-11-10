<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class ContextualBindingsTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    public function test_contextual_binding_resolves_based_on_requesting_class(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addBinding('LoggerInterface', 'DefaultLogger');
            $config->when(stdClass::class)
                ->needs('LoggerInterface')
                ->give('SpecificLogger');
        });

        Container::withConfig(Config::getInstance());

        // Verify the contextual binding was registered
        $contextualBindings = Config::getInstance()
            ->getSetupGacela()
            ->getContextualBindings();

        self::assertArrayHasKey(stdClass::class, $contextualBindings);
        self::assertSame('SpecificLogger', $contextualBindings[stdClass::class]['LoggerInterface']);
    }

    public function test_contextual_binding_with_callable(): void
    {
        $callable = static fn (): stdClass => new stdClass();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($callable): void {
            $config->when('UserController')
                ->needs('LoggerInterface')
                ->give($callable);
        });

        Container::withConfig(Config::getInstance());

        $contextualBindings = Config::getInstance()
            ->getSetupGacela()
            ->getContextualBindings();

        self::assertArrayHasKey('UserController', $contextualBindings);
        self::assertSame($callable, $contextualBindings['UserController']['LoggerInterface']);
    }

    public function test_multiple_contextual_bindings_for_same_class(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->when(stdClass::class)
                ->needs('LoggerInterface')
                ->give('FileLogger');

            $config->when(stdClass::class)
                ->needs('CacheInterface')
                ->give('RedisCache');
        });

        Container::withConfig(Config::getInstance());

        $contextualBindings = Config::getInstance()
            ->getSetupGacela()
            ->getContextualBindings();

        self::assertArrayHasKey(stdClass::class, $contextualBindings);
        self::assertCount(2, $contextualBindings[stdClass::class]);
        self::assertSame('FileLogger', $contextualBindings[stdClass::class]['LoggerInterface']);
        self::assertSame('RedisCache', $contextualBindings[stdClass::class]['CacheInterface']);
    }

    public function test_contextual_binding_with_multiple_classes(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->when([stdClass::class, 'OtherClass'])
                ->needs('LoggerInterface')
                ->give('SharedLogger');
        });

        Container::withConfig(Config::getInstance());

        $contextualBindings = Config::getInstance()
            ->getSetupGacela()
            ->getContextualBindings();

        self::assertArrayHasKey(stdClass::class, $contextualBindings);
        self::assertArrayHasKey('OtherClass', $contextualBindings);
        self::assertSame('SharedLogger', $contextualBindings[stdClass::class]['LoggerInterface']);
        self::assertSame('SharedLogger', $contextualBindings['OtherClass']['LoggerInterface']);
    }

    public function test_contextual_bindings_merge_with_gacela_php_file(): void
    {
        // Create a temporary gacela.php file
        $tempDir = sys_get_temp_dir() . '/gacela_test_' . uniqid('', true);
        mkdir($tempDir);

        $gacelaPhpContent = <<<'PHP'
<?php
use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config->when('FromFile')
        ->needs('LoggerInterface')
        ->give('FileLogger');
};
PHP;

        file_put_contents($tempDir . '/gacela.php', $gacelaPhpContent);

        Gacela::bootstrap($tempDir, static function (GacelaConfig $config): void {
            $config->when('FromBootstrap')
                ->needs('CacheInterface')
                ->give('RedisCache');
        });

        Container::withConfig(Config::getInstance());

        $contextualBindings = Config::getInstance()
            ->getSetupGacela()
            ->getContextualBindings();

        // Both bindings should be present
        self::assertArrayHasKey('FromFile', $contextualBindings);
        self::assertArrayHasKey('FromBootstrap', $contextualBindings);
        self::assertSame('FileLogger', $contextualBindings['FromFile']['LoggerInterface']);
        self::assertSame('RedisCache', $contextualBindings['FromBootstrap']['CacheInterface']);

        // Cleanup
        unlink($tempDir . '/gacela.php');
        rmdir($tempDir);
    }
}
