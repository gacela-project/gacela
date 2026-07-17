<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CacheClear;

use Gacela\Console\Infrastructure\Command\CacheClearCommand;
use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function dirname;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function unlink;

final class CacheClearCommandTest extends TestCase
{
    private string $cacheFile;

    private string $mergedConfigCacheFile;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__ . '/cache');
        });

        $this->cacheFile = Config::getInstance()->getCacheDir() . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;
        $this->mergedConfigCacheFile = Config::getInstance()->mergedConfigCacheFilename();

        $this->removeGeneratedCaches();
    }

    protected function tearDown(): void
    {
        $this->removeGeneratedCaches();
        DirectoryUtil::removeDir(__DIR__ . '/cache');
    }

    public function test_cache_clear_command_is_registered_in_the_console_application(): void
    {
        $command = (new ConsoleBootstrap())->find('cache:clear');

        self::assertSame('cache:clear', $command->getName());
    }

    public function test_cache_clear_removes_an_existing_cache_file(): void
    {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        file_put_contents($this->cacheFile, '<?php return [];');

        $command = new CommandTester(new CacheClearCommand());
        $exitCode = $command->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Cleared cache file', $command->getDisplay());
        self::assertStringContainsString('Cache cleared successfully!', $command->getDisplay());
        self::assertFileDoesNotExist($this->cacheFile);
    }

    public function test_cache_clear_reports_when_no_cache_is_present(): void
    {
        $command = new CommandTester(new CacheClearCommand());
        $exitCode = $command->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No cache files found.', $command->getDisplay());
    }

    private function removeGeneratedCaches(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        if (file_exists($this->mergedConfigCacheFile)) {
            unlink($this->mergedConfigCacheFile);
        }
    }
}
