<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CacheWarm;

use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function dirname;
use function file_exists;
use function unlink;

final class CacheWarmCommandTest extends TestCase
{
    private CommandTester $command;

    private string $cacheFile;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__ . '/cache');
        });

        $this->cacheFile = Config::getInstance()->getCacheDir() . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME;

        // Clean up cache file before test
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        $this->command = new CommandTester(new CacheWarmCommand());
    }

    protected function tearDown(): void
    {
        // Clean up cache file after test
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function test_cache_warm_creates_cache_file(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Warming Gacela cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertStringContainsString('Modules processed:', $output);
        self::assertStringContainsString('Classes resolved:', $output);
        self::assertStringContainsString('Classes skipped:', $output);
        self::assertStringContainsString('Time taken:', $output);
        self::assertStringContainsString('Memory used:', $output);
    }

    public function test_cache_warm_with_clear_option(): void
    {
        // Ensure cache directory exists
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Create a cache file first
        file_put_contents($this->cacheFile, '<?php return [];');
        self::assertFileExists($this->cacheFile);

        $this->command->execute(['--clear' => true]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Cleared existing cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
    }

    public function test_cache_warm_finds_test_modules(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        // Should find at least the test facade in this directory
        self::assertStringContainsString('Found', $output);
        self::assertStringContainsString('modules', $output);
    }

    public function test_cache_warm_displays_statistics(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        // Check for statistics
        self::assertMatchesRegularExpression('/Modules processed:\s+\d+/', $output);
        self::assertMatchesRegularExpression('/Classes resolved:\s+\d+/', $output);
        self::assertMatchesRegularExpression('/Classes skipped:\s+\d+/', $output);
        self::assertMatchesRegularExpression('/Time taken:\s+[\d.]+\s+seconds/', $output);
        self::assertMatchesRegularExpression('/Memory used:\s+[\d.]+\s+(B|KB|MB)/', $output);
    }

    public function test_cache_warm_success_exit_code(): void
    {
        $exitCode = $this->command->execute([]);

        self::assertSame(0, $exitCode);
    }

    public function test_cache_warm_with_attributes_option(): void
    {
        $this->command->execute(['--attributes' => true]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Warming Gacela cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertSame(0, $this->command->getStatusCode());
    }

    public function test_cache_warm_with_all_options(): void
    {
        $this->command->execute([
            '--clear' => true,
            '--attributes' => true,
        ]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Cleared existing cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertSame(0, $this->command->getStatusCode());
    }

    public function test_cache_warm_with_parallel_option(): void
    {
        $this->command->execute(['--parallel' => true]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Warming Gacela cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertSame(0, $this->command->getStatusCode());
    }

    public function test_cache_warm_with_all_performance_options(): void
    {
        $this->command->execute([
            '--clear' => true,
            '--attributes' => true,
            '--parallel' => true,
        ]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Cleared existing cache', $output);
        self::assertStringContainsString('Cache warming complete!', $output);
        self::assertMatchesRegularExpression('/Time taken:\s+[\d.]+\s+seconds/', $output);
        self::assertSame(0, $this->command->getStatusCode());
    }
}
