<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console;

use Closure;
use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;

use PHPUnit\Framework\TestCase;

use function bin2hex;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * The suffix set is configurable, and a project on `addSuffixTypeFacade()` has
 * to get the same answer for the name it actually uses. The unit tests hand the
 * finder its suffixes directly, so nothing there exercises the wiring that
 * reads them out of the project's own configuration.
 */
final class UndiscoveredFacadeSuffixTest extends TestCase
{
    private string $appRoot = '';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-suffix-' . bin2hex(random_bytes(4));
        mkdir($this->appRoot . DIRECTORY_SEPARATOR . 'Blog', 0777, true);
    }

    protected function tearDown(): void
    {
        // Names exactly what this test created.
        foreach ($this->created as $file) {
            self::assertStringStartsWith($this->appRoot . DIRECTORY_SEPARATOR, $file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ([$this->appRoot . DIRECTORY_SEPARATOR . 'Blog', $this->appRoot] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        $this->created = [];
        Gacela::resetCache();
    }

    public function test_a_project_configured_suffix_is_reported_like_a_facade(): void
    {
        $this->writeClass('BlogPublicApi');

        $found = $this->bootstrapAndFind(static function (GacelaConfig $config): void {
            $config->addSuffixTypeFacade('PublicApi');
        });

        self::assertCount(1, $found);
        self::assertSame('Nowhere\\Nothing\\Blog\\BlogPublicApi', $found[0]->className);
    }

    /**
     * The same file, unconfigured. Without this the test above would pass for a
     * finder that reports every unloadable class whatever it is called.
     */
    public function test_the_same_file_is_ignored_when_the_suffix_is_not_configured(): void
    {
        $this->writeClass('BlogPublicApi');

        self::assertSame([], $this->bootstrapAndFind(static function (GacelaConfig $config): void {
        }));
    }

    /**
     * The built-in suffix keeps working beside a configured one, which is what
     * reading the configured list *on top of* the built-in ones buys.
     */
    public function test_the_built_in_suffix_survives_a_configured_one(): void
    {
        $this->writeClass('BlogFacade');

        $found = $this->bootstrapAndFind(static function (GacelaConfig $config): void {
            $config->addSuffixTypeFacade('PublicApi');
        });

        self::assertCount(1, $found);
        self::assertSame('Nowhere\\Nothing\\Blog\\BlogFacade', $found[0]->className);
    }

    /**
     * @param Closure(GacelaConfig):void $configFn
     *
     * @return list<\Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeFile>
     */
    private function bootstrapAndFind(Closure $configFn): array
    {
        $appRoot = $this->appRoot;

        Gacela::bootstrap($appRoot, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->setAppModulePaths(['.']);
            $configFn($config);
        });

        return (new ConsoleFacade())->findUndiscoveredFacadeFiles();
    }

    private function writeClass(string $className): void
    {
        $path = $this->appRoot . DIRECTORY_SEPARATOR . 'Blog' . DIRECTORY_SEPARATOR . $className . '.php';

        // A namespace no autoloader maps, so the class cannot load -- which is
        // the fault, not an accident of the fixture.
        file_put_contents($path, sprintf(
            "<?php\n\nnamespace Nowhere\\Nothing\\Blog;\n\nclass %s\n{\n}\n",
            $className,
        ));

        $this->created[] = $path;
    }
}
