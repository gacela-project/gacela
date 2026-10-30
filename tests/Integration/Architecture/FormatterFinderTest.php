<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use GacelaTest\Feature\Util\DirectoryUtil;
use PhpCsFixer\ConfigInterface;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function dirname;
use function strlen;

final class FormatterFinderTest extends TestCase
{
    private const SOURCE_FILES = [
        'bridges/laravel-bridge/tests/CheckedTest.php',
        'bridges/symfony-bridge/src/Checked.php',
        'src/Checked.php',
        'tests/Fixture/gacela-local.php',
        'tests/Fixture/gacela-prod.php',
        'tests/Fixture/gacela.php',
    ];

    private const CACHE_FILES = [
        'tests/cache/gacela-class-names-hash.php',
        'tests/cache/gacela-custom-services-hash.php',
        'tests/cache/gacela-container-plans-hash.php',
        'tests/cache/gacela-merged-config.php',
        'tests/cache/gacela-merged-config-v2-hash.php',
        'tests/cache/.gacelagacela-merged-config-hash.php',
        'tests/cache/warmer-cachegacela-merged-config-hash.php',
        'tests/.gacela/cache/gacela-membership-hash.php',
        'tests/.gacela-cache-hash/generated.php',
        'tests/.cache/generated.php',
        'tests/compiled.cache/generated.php',
        'bridges/symfony-bridge/tests/cache/gacela-class-names-hash.php',
        'bridges/laravel-bridge/tests/cache/gacela-container-plans-hash.php',
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gacela-formatter-' . bin2hex(random_bytes(6));
        foreach (['src', 'tests', 'bridges/symfony-bridge/src', 'bridges/symfony-bridge/tests', 'bridges/laravel-bridge/src', 'bridges/laravel-bridge/tests'] as $directory) {
            mkdir($this->root . '/' . $directory, 0777, true);
        }

        $canonical = realpath($this->root);
        self::assertIsString($canonical);
        $this->root = $canonical;
        $repository = dirname(__DIR__, 3);
        copy($repository . '/.php-cs-fixer.dist.php', $this->root . '/.php-cs-fixer.dist.php');
        copy($repository . '/.gitignore', $this->root . '/.gitignore');
        file_put_contents($this->root . '/.git', 'gitdir: external-worktree-metadata');
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir($this->root);
    }

    public function test_a_git_file_keeps_generated_caches_out_and_source_fixtures_in(): void
    {
        foreach ([...self::SOURCE_FILES, ...self::CACHE_FILES] as $path) {
            $this->writePhpFile($path);
        }

        self::assertSame(self::SOURCE_FILES, $this->finderPaths());
    }

    private function writePhpFile(string $relative): void
    {
        $path = $this->root . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, "<?php\n");
    }

    /**
     * @return list<string>
     */
    private function finderPaths(): array
    {
        $config = require $this->root . '/.php-cs-fixer.dist.php';
        self::assertInstanceOf(ConfigInterface::class, $config);
        $paths = [];
        foreach ($config->getFinder() as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
        }

        sort($paths);

        return $paths;
    }
}
