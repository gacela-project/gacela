<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\CacheStalenessCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use PHPUnit\Framework\TestCase;
use stdClass;

use function file_put_contents;
use function is_string;
use function mkdir;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;
use function var_export;

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
            if (is_string($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->tempDir);
    }

    public function test_missing_cache_dir_returns_ok(): void
    {
        $result = (new CacheStalenessCheck('/nonexistent/path/xyz'))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no cache directory — nothing to check'], $result->details);
    }

    public function test_unconfigured_cache_dir_returns_ok(): void
    {
        $result = (new CacheStalenessCheck(''))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no cache directory — nothing to check'], $result->details);
    }

    public function test_empty_cache_dir_returns_ok(): void
    {
        $result = (new CacheStalenessCheck($this->tempDir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['all cache entries are fresh'], $result->details);
    }

    public function test_fresh_cache_returns_ok(): void
    {
        $sourceFile = $this->writeSource(time() - 120);
        $this->writeCache(ClassNamePhpCache::FILENAME, ['SomeKey' => 'Some\\Class'], time());

        $check = new CacheStalenessCheck($this->tempDir, static fn (string $className): string => $sourceFile);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_source_newer_than_cache_returns_warn(): void
    {
        $sourceFile = $this->writeSource(time());
        $this->writeCache(ClassNamePhpCache::FILENAME, ['SomeKey' => 'Some\\Class'], time() - 120);

        $check = new CacheStalenessCheck($this->tempDir, static fn (string $className): string => $sourceFile);

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['stale: SomeKey → Some\\Class'], $result->details);
        self::assertSame('run `bin/gacela cache:clear && bin/gacela cache:warm` to rebuild', $result->remediation);
    }

    /**
     * A source touched at the very same second as the cache was written is the
     * normal outcome of `cache:warm`, so it must not be reported as stale.
     */
    public function test_source_with_the_same_mtime_as_the_cache_is_not_stale(): void
    {
        $when = time() - 60;
        $sourceFile = $this->writeSource($when);
        $this->writeCache(ClassNamePhpCache::FILENAME, ['SomeKey' => 'Some\\Class'], $when);

        $check = new CacheStalenessCheck($this->tempDir, static fn (string $className): string => $sourceFile);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_unresolvable_source_is_reported(): void
    {
        $this->writeCache(ClassNamePhpCache::FILENAME, ['SomeKey' => 'Ghost\\Class'], time());

        $check = new CacheStalenessCheck($this->tempDir, static fn (string $className): ?string => null);

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['missing source: SomeKey → Ghost\\Class (source file not found)'], $result->details);
    }

    public function test_an_unresolvable_entry_does_not_hide_the_entries_after_it(): void
    {
        $sourceFile = $this->writeSource(time());
        $this->writeCache(
            ClassNamePhpCache::FILENAME,
            ['GhostKey' => 'Ghost\\Class', 'RealKey' => 'Real\\Class'],
            time() - 120,
        );

        $check = new CacheStalenessCheck(
            $this->tempDir,
            static fn (string $className): ?string => $className === 'Ghost\\Class' ? null : $sourceFile,
        );

        self::assertSame([
            'stale: RealKey → Real\\Class',
            'missing source: GhostKey → Ghost\\Class (source file not found)',
        ], $check->run()->details);
    }

    public function test_an_entry_pointing_at_a_deleted_file_does_not_hide_the_entries_after_it(): void
    {
        $sourceFile = $this->writeSource(time());
        $deletedFile = $this->tempDir . '/Deleted.php';
        $this->writeCache(
            ClassNamePhpCache::FILENAME,
            ['GoneKey' => 'Gone\\Class', 'RealKey' => 'Real\\Class'],
            time() - 120,
        );

        $check = new CacheStalenessCheck(
            $this->tempDir,
            static fn (string $className): string => $className === 'Gone\\Class' ? $deletedFile : $sourceFile,
        );

        self::assertSame([
            'stale: RealKey → Real\\Class',
            'missing source: GoneKey → Gone\\Class (' . $deletedFile . ')',
        ], $check->run()->details);
    }

    /**
     * The class-name cache is optional: warming only the custom-services cache
     * still has to be checked, so a missing first file cannot end the scan.
     */
    public function test_the_custom_services_cache_is_checked_when_the_class_name_cache_is_missing(): void
    {
        $sourceFile = $this->writeSource(time());
        $this->writeCache(CustomServicesPhpCache::FILENAME, ['SomeKey' => 'Some\\Class'], time() - 120);

        $check = new CacheStalenessCheck($this->tempDir, static fn (string $className): string => $sourceFile);

        self::assertSame(['stale: SomeKey → Some\\Class'], $check->run()->details);
    }

    public function test_the_default_resolver_finds_the_source_of_a_real_class(): void
    {
        $this->writeCache(
            ClassNamePhpCache::FILENAME,
            ['SomeKey' => CacheStalenessCheck::class],
            time() + 3600,
        );

        $result = (new CacheStalenessCheck($this->tempDir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['all cache entries are fresh'], $result->details);
    }

    public function test_the_default_resolver_reports_a_class_php_has_no_source_file_for(): void
    {
        $this->writeCache(ClassNamePhpCache::FILENAME, ['SomeKey' => stdClass::class], time());

        $result = (new CacheStalenessCheck($this->tempDir))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['missing source: SomeKey → stdClass (source file not found)'], $result->details);
    }

    /**
     * The entry a stale cache most often holds: a class that was renamed or
     * deleted, so the name resolves to neither a class nor an interface. It
     * must be reported rather than handed to ReflectionClass, which would
     * throw on a name that does not exist.
     */
    public function test_the_default_resolver_reports_a_class_that_no_longer_exists(): void
    {
        $this->writeCache(ClassNamePhpCache::FILENAME, ['SomeKey' => 'Some\\Deleted\\Class'], time());

        $result = (new CacheStalenessCheck($this->tempDir))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['missing source: SomeKey → Some\\Deleted\\Class (source file not found)'],
            $result->details,
        );
    }

    /**
     * Interfaces are cached like any other resolvable type, so the default
     * resolver has to find their source file too, not just classes'.
     */
    public function test_the_default_resolver_finds_the_source_of_an_interface(): void
    {
        $this->writeCache(
            ClassNamePhpCache::FILENAME,
            ['SomeKey' => HealthCheck::class],
            time() + 3600,
        );

        $result = (new CacheStalenessCheck($this->tempDir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['all cache entries are fresh'], $result->details);
    }

    private function writeSource(int $mtime): string
    {
        $sourceFile = $this->tempDir . '/Source.php';
        file_put_contents($sourceFile, '<?php');
        touch($sourceFile, $mtime);

        return $sourceFile;
    }

    /**
     * @param array<string, string> $entries
     */
    private function writeCache(string $filename, array $entries, int $mtime): string
    {
        $cacheFile = $this->tempDir . '/' . $filename;
        file_put_contents($cacheFile, '<?php return ' . var_export($entries, true) . ';');
        touch($cacheFile, $mtime);

        return $cacheFile;
    }
}
