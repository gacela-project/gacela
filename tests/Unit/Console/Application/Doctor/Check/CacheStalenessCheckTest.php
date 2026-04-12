<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\CacheStalenessCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use PHPUnit\Framework\TestCase;

use function is_string;

final class CacheStalenessCheckTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gacela-doctor-' . uniqid('', true);
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tempDir . '/*') as $file) {
            is_string($file) && @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    public function test_missing_cache_dir_returns_ok(): void
    {
        $check = new CacheStalenessCheck('/nonexistent/path/xyz');

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_empty_cache_dir_returns_ok(): void
    {
        $check = new CacheStalenessCheck($this->tempDir);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_fresh_cache_returns_ok(): void
    {
        $sourceFile = $this->tempDir . '/Source.php';
        file_put_contents($sourceFile, '<?php');
        touch($sourceFile, time() - 120);

        $cacheFile = $this->tempDir . '/' . ClassNamePhpCache::FILENAME;
        file_put_contents(
            $cacheFile,
            '<?php return ' . var_export(['SomeKey' => 'Some\\Class'], true) . ';',
        );
        touch($cacheFile, time());

        $check = new CacheStalenessCheck(
            $this->tempDir,
            static fn (string $className): string => $sourceFile,
        );

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_source_newer_than_cache_returns_warn(): void
    {
        $sourceFile = $this->tempDir . '/Source.php';
        file_put_contents($sourceFile, '<?php');
        touch($sourceFile, time());

        $cacheFile = $this->tempDir . '/' . ClassNamePhpCache::FILENAME;
        file_put_contents(
            $cacheFile,
            '<?php return ' . var_export(['SomeKey' => 'Some\\Class'], true) . ';',
        );
        touch($cacheFile, time() - 120);

        $check = new CacheStalenessCheck(
            $this->tempDir,
            static fn (string $className): string => $sourceFile,
        );

        $result = $check->run();
        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertNotEmpty($result->details);
    }

    public function test_unresolvable_source_is_reported(): void
    {
        $cacheFile = $this->tempDir . '/' . ClassNamePhpCache::FILENAME;
        file_put_contents(
            $cacheFile,
            '<?php return ' . var_export(['SomeKey' => 'Ghost\\Class'], true) . ';',
        );

        $check = new CacheStalenessCheck(
            $this->tempDir,
            static fn (string $className): ?string => null,
        );

        $result = $check->run();
        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('missing source', $result->details[0] ?? '');
    }
}
