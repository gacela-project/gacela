<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BootstrapReadsGacelaPhpOnce;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\BootstrapReadsGacelaPhpOnce\Module\CountingPlugin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Bootstrapping without a closure is the form real applications use: the
 * configuration lives in `gacela.php` and nothing is passed to
 * {@see Gacela::bootstrap()}.
 *
 * Every other test of this behaviour passes a closure, which is why the file
 * being read twice went unnoticed -- the closure path reads it once.
 */
final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        CountingPlugin::reset();
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
        CountingPlugin::reset();
    }

    #[DataProvider('rootSuffixes')]
    public function test_root_spelling_keeps_one_plugin_and_one_config_item(string $suffix): void
    {
        Gacela::bootstrap(__DIR__ . $suffix);

        self::assertSame(1, CountingPlugin::$runs);
        self::assertCount(1, Config::getInstance()->getFactory()->createGacelaFileConfig()->getConfigItems());
        self::assertSame(__DIR__, Gacela::rootDir());
        self::assertSame(Gacela::rootDir(), Config::getInstance()->getAppRootDir());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rootSuffixes(): iterable
    {
        yield 'plain' => [''];
        yield 'trailing separator' => [DIRECTORY_SEPARATOR];
        yield 'repeated trailing separators' => [str_repeat(DIRECTORY_SEPARATOR, 3)];
    }

    public function test_root_normalization_preserves_filesystem_roots_and_empty_input(): void
    {
        $normalize = new ReflectionMethod(Gacela::class, 'normalizeRootDir');

        foreach (['/', '\\', 'C:\\', 'C:/', '', '0'] as $root) {
            self::assertSame($root, $normalize->invoke(null, $root));
        }
    }

    public function test_a_plugin_declared_once_runs_once(): void
    {
        Gacela::bootstrap(__DIR__);

        self::assertSame(1, CountingPlugin::$runs);
    }

    public function test_a_config_path_declared_once_is_one_config_item(): void
    {
        Gacela::bootstrap(__DIR__);

        $configItems = Config::getInstance()
            ->getFactory()
            ->createGacelaFileConfig()
            ->getConfigItems();

        self::assertCount(1, $configItems);
    }

    /**
     * The values still have to arrive -- reading the file once is only an
     * improvement if it is the once that counts.
     */
    public function test_the_configuration_is_still_loaded(): void
    {
        Gacela::bootstrap(__DIR__);

        self::assertSame('gacela-php-once', Config::getInstance()->get('from'));
    }

    public function test_the_declared_plugin_is_registered_once(): void
    {
        Gacela::bootstrap(__DIR__);

        self::assertCount(1, Config::getInstance()->getSetupGacela()->getPlugins());
    }
}
