<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\CacheStalenessCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Config\MergedConfigCache;
use PHPUnit\Framework\TestCase;
use stdClass;

use function dirname;
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
        // Depth-first: the merged-config tests write into a `config/` subdir,
        // and a non-empty directory cannot be removed.
        foreach ((array) glob($this->tempDir . '/*/*') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }

        foreach ((array) glob($this->tempDir . '/*') as $file) {
            if (is_string($file)) {
                @unlink($file);
                @rmdir($file);
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

    /**
     * The merged configuration cache keeps serving values after a source config
     * file changes, so doctor reported "all cache entries are fresh" while the
     * active configuration was stale.
     */
    public function test_a_config_source_newer_than_the_merged_cache_is_reported(): void
    {
        $source = $this->writeConfigSource('config/default.php', time());
        $this->writeMergedConfigCache(time() - 120);

        $result = $this->checkWithMergedConfig([$source])->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['stale: merged config ← ' . $source], $result->details);
        self::assertSame('run `bin/gacela cache:clear && bin/gacela cache:warm` to rebuild', $result->remediation);
    }

    /**
     * The environment file is a separate pattern from the base one, and was
     * equally invisible.
     */
    public function test_a_stale_environment_config_source_is_reported(): void
    {
        $source = $this->writeConfigSource('config/default.dev.php', time());
        $this->writeMergedConfigCache(time() - 120);

        $result = $this->checkWithMergedConfig([$source])->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['stale: merged config ← ' . $source], $result->details);
    }

    /**
     * The local override is merged last and wins over everything, so a stale one
     * is the most misleading of the three.
     */
    public function test_a_stale_local_override_source_is_reported(): void
    {
        $source = $this->writeConfigSource('config/local.php', time());
        $this->writeMergedConfigCache(time() - 120);

        $result = $this->checkWithMergedConfig([$source])->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['stale: merged config ← ' . $source], $result->details);
    }

    public function test_a_merged_cache_newer_than_every_source_stays_ok(): void
    {
        $base = $this->writeConfigSource('config/default.php', time() - 120);
        $local = $this->writeConfigSource('config/local.php', time() - 120);
        $this->writeMergedConfigCache(time());

        $result = $this->checkWithMergedConfig([$base, $local])->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['all cache entries are fresh'], $result->details);
    }

    /**
     * A source the cache was built from and that has since been deleted changes
     * the merged result just as much as an edited one.
     */
    public function test_a_config_source_that_no_longer_exists_is_reported(): void
    {
        $this->writeMergedConfigCache(time());

        $result = $this->checkWithMergedConfig([$this->tempDir . '/config/gone.php'])->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['missing source: merged config ← ' . $this->tempDir . '/config/gone.php'],
            $result->details,
        );
    }

    /**
     * A config file written in the same second the cache was warmed is the
     * normal outcome of `cache:warm`, so it must not be reported as stale --
     * the same rule the class-name cache already follows.
     */
    public function test_a_config_source_with_the_same_mtime_as_the_merged_cache_is_not_stale(): void
    {
        $when = time() - 60;
        $source = $this->writeConfigSource('config/default.php', $when);
        $this->writeMergedConfigCache($when);

        self::assertSame(CheckStatus::Ok, $this->checkWithMergedConfig([$source])->run()->status);
    }

    /**
     * A deleted source must not stop the scan: the sources after it are equally
     * capable of being stale, and reporting only the first hides the rest.
     */
    public function test_a_missing_config_source_does_not_hide_the_sources_after_it(): void
    {
        $gone = $this->tempDir . '/config/gone.php';
        $stale = $this->writeConfigSource('config/default.php', time());
        $this->writeMergedConfigCache(time() - 120);

        $result = $this->checkWithMergedConfig([$gone, $stale])->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            [
                'stale: merged config ← ' . $stale,
                'missing source: merged config ← ' . $gone,
            ],
            $result->details,
        );
    }

    /**
     * Without a merged cache on disk there is nothing to be stale against: the
     * next bootstrap rebuilds it from the sources.
     */
    public function test_sources_are_not_checked_when_no_merged_cache_exists(): void
    {
        $this->writeConfigSource('config/default.php', time());

        $result = $this->checkWithMergedConfig([$this->tempDir . '/config/default.php'])->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    /**
     * @param list<string> $sources
     */
    private function checkWithMergedConfig(array $sources): HealthCheck
    {
        return new CacheStalenessCheck(
            $this->tempDir,
            static fn (string $className): ?string => null,
            '',
            new MergedConfigCache($this->tempDir),
            $sources,
        );
    }

    private function writeConfigSource(string $relativePath, int $mtime): string
    {
        $path = $this->tempDir . '/' . $relativePath;
        @mkdir(dirname($path), 0o777, true);
        file_put_contents($path, '<?php return [];');
        touch($path, $mtime);

        return $path;
    }

    private function writeMergedConfigCache(int $mtime): string
    {
        $file = (new MergedConfigCache($this->tempDir))->filename();
        file_put_contents($file, '<?php return [];');
        touch($file, $mtime);

        return $file;
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
