<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use ArrayObject;
use Closure;
use Gacela\Framework\Cache\WritableDirectory;
use Gacela\Framework\Config\ConfigSourceStamps;
use Gacela\Framework\Config\MergedConfigCache;
use GacelaTest\Fixtures\ReadOnlyDirTrait;
use GacelaTest\Fixtures\SortDirection;
use PHPUnit\Framework\Attributes\DataProvider;

use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function rmdir;
use function sprintf;
use function str_replace;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

final class MergedConfigCacheTest extends TestCase
{
    use ReadOnlyDirTrait;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-merged-config-test-' . uniqid('', true);
        WritableDirectory::resetCache();
    }

    protected function tearDown(): void
    {
        WritableDirectory::resetCache();
        $this->restoreReadOnlyDirs();
        $this->removeCacheDirIfExists();
    }

    public function test_a_trusted_file_is_served_without_a_look_at_any_source(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted(['key' => 'value']);

        self::assertSame(['key' => 'value'], $cache->loadIfCurrent(static fn (): string => 'declared'));
    }

    public function test_a_verified_file_is_served_while_its_sources_are_unchanged(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeVerified(['key' => 'value'], 'declared', ConfigSourceStamps::of([__FILE__]));

        self::assertSame(['key' => 'value'], $cache->loadIfCurrent(static fn (): string => 'declared'));
    }

    public function test_a_verified_file_is_not_served_once_a_source_changed(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeVerified(['key' => 'value'], 'declared', [__FILE__ => 'a stamp it never had']);

        self::assertNull($cache->loadIfCurrent(static fn (): string => 'declared'));
    }

    public function test_a_verified_file_for_other_declarations_is_not_served(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeVerified(['key' => 'value'], 'declared', ConfigSourceStamps::of([__FILE__]));

        self::assertNull($cache->loadIfCurrent(static fn (): string => 'declared differently'));
    }

    /**
     * A deploy artifact: the code that declares the config paths ships with the
     * `cache:warm` that wrote it, so the signature is not even computed.
     */
    public function test_a_trusted_file_does_not_ask_for_the_declarations(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted(['key' => 'value']);

        self::assertSame(['key' => 'value'], $cache->loadIfCurrent(static fn (): string => self::fail('asked for the declarations')));
    }

    /**
     * Written on a miss but not in the shape this version writes: nothing says
     * what it answers for, so it is rebuilt rather than trusted.
     *
     * @param array<string,mixed> $content
     */
    #[DataProvider('filesThatAreNeverCurrent')]
    public function test_a_file_of_another_shape_is_never_current(array $content): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted([]);
        file_put_contents($cache->filename(), sprintf('<?php return %s;', var_export($content, true)));

        self::assertNull($cache->loadIfCurrent(static fn (): string => 'declared'));
    }

    /**
     * @return iterable<string, array{array<string,mixed>}>
     */
    public static function filesThatAreNeverCurrent(): iterable
    {
        $header = "\0gacela-merged-config-sources";

        yield 'a header that is not a string' => [[$header => ['declared'], 'values' => ['key' => 'value']]];
        yield 'values that are not an array' => [[$header => "declared\n", 'values' => 'value']];
        yield 'no values' => [[$header => "declared\n"]];
    }

    /**
     * @param array<string,mixed> $values
     */
    #[DataProvider('valuesThatCannotBeReadBack')]
    public function test_values_that_cannot_be_read_back_are_not_cached(array $values): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->writeTrusted($values);
        self::assertFalse($cache->exists(), 'trusted');

        $cache->writeVerified($values, 'declared', ConfigSourceStamps::of([__FILE__]));
        self::assertFalse($cache->exists(), 'verified');
    }

    /**
     * @return iterable<string, array{array<string,mixed>}>
     */
    public static function valuesThatCannotBeReadBack(): iterable
    {
        yield 'a closure' => [['handler' => static fn (): string => 'hi']];
        yield 'a nested closure' => [['handlers' => ['a' => 'b', 'c' => [static fn (): null => null]]]];
        yield 'an object without __set_state()' => [['clock' => new ArrayObject()]];
        yield 'a closure inside a plain object' => [['options' => (object) ['on' => static fn (): null => null]]];
    }

    public function test_values_that_cannot_be_read_back_remove_an_older_file(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted(['key' => 'old']);

        $cache->writeTrusted(['key' => 'new', 'handler' => static fn (): string => 'hi']);

        self::assertFalse($cache->exists());
    }

    public function test_values_that_export_as_loadable_code_are_cached(): void
    {
        $values = [
            'plain' => (object) ['a' => 1],
            'enum' => SortDirection::Asc,
            'nested' => ['list' => [1, 2.5, true, null]],
        ];
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->writeTrusted($values);

        self::assertEquals($values, $cache->loadIfCurrent(static fn (): string => 'declared'));
    }

    public function test_a_file_that_fails_to_load_is_a_miss(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted([]);
        file_put_contents($cache->filename(), "<?php return ['handler' => " . Closure::class . '::__set_state([])];');

        self::assertNull($cache->loadIfCurrent(static fn (): string => 'declared'));
        self::assertSame([], $cache->load());
    }

    public function test_write_is_best_effort_when_the_cache_directory_cannot_be_created(): void
    {
        $cache = new MergedConfigCache($this->uncreatableDir());

        $cache->writeTrusted(['key' => 'value']);

        self::assertFalse($cache->exists());
    }

    public function test_write_is_best_effort_when_the_cache_directory_is_read_only(): void
    {
        $cache = new MergedConfigCache($this->createReadOnlyDirOrSkip('merged-config-readonly'));

        $cache->writeTrusted(['key' => 'value']);

        self::assertFalse($cache->exists());
    }

    public function test_exists_is_false_when_file_not_written(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        self::assertFalse($cache->exists());
    }

    public function test_write_creates_the_cache_file(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->writeTrusted(['key' => 'value']);

        self::assertTrue($cache->exists());
    }

    public function test_load_returns_written_data(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted(['key' => 'value', 'nested' => ['a' => 1]]);

        self::assertSame(['key' => 'value', 'nested' => ['a' => 1]], $cache->load());
    }

    public function test_write_overwrites_previous_content(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted(['old' => 'data']);

        $cache->writeTrusted(['new' => 'data']);

        self::assertSame(['new' => 'data'], $cache->load());
    }

    public function test_clear_removes_the_cache_file(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);
        $cache->writeTrusted(['key' => 'value']);

        $cache->clear();

        self::assertFalse($cache->exists());
    }

    public function test_clear_is_noop_when_file_does_not_exist(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->clear();

        self::assertFalse($cache->exists());
    }

    public function test_filename_has_no_env_suffix_when_env_empty(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        self::assertStringEndsWith(
            MergedConfigCache::FILENAME_PREFIX . '-v2' . MergedConfigCache::FILENAME_EXTENSION,
            $cache->filename(),
        );
    }

    public function test_filename_includes_env_suffix_when_env_set(): void
    {
        $cache = new MergedConfigCache($this->cacheDir, 'prod');

        self::assertStringEndsWith(
            MergedConfigCache::FILENAME_PREFIX . '-v2-prod' . MergedConfigCache::FILENAME_EXTENSION,
            $cache->filename(),
        );
    }

    public function test_filename_embeds_the_exact_app_root_hash_and_env_suffix(): void
    {
        $cache = new MergedConfigCache($this->cacheDir, 'prod', '/app/root');

        self::assertSame(
            $this->cacheDir . DIRECTORY_SEPARATOR
                . MergedConfigCache::FILENAME_PREFIX
                . '-v2'
                . '-' . substr(sha1('/app/root'), 0, 12)
                . '-prod'
                . MergedConfigCache::FILENAME_EXTENSION,
            $cache->filename(),
        );
    }

    /**
     * The BC lock: a project declaring no dimension must keep the filename it
     * had, or every upgrade silently invalidates a warm cache.
     */
    public function test_declaring_no_dimension_keeps_the_existing_filename(): void
    {
        $before = new MergedConfigCache($this->cacheDir, 'prod', '/app/root');
        $after = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', []);

        self::assertSame($before->filename(), $after->filename());
    }

    public function test_a_declared_dimension_gives_the_file_its_own_name(): void
    {
        $plain = new MergedConfigCache($this->cacheDir, 'prod', '/app/root');
        $eu = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu']);

        self::assertNotSame($plain->filename(), $eu->filename());
    }

    public function test_two_dimension_tuples_never_share_a_file(): void
    {
        $eu = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu']);
        $us = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['us']);

        self::assertNotSame($eu->filename(), $us->filename());
    }

    /**
     * The collision the readable-segment spelling would have shipped:
     * `-{env}-{region}` cannot tell `prod-eu` with no region from `prod` in
     * region `eu`, and hyphenated env names are ordinary. Hashing the
     * dimensions keeps the two apart.
     */
    public function test_a_hyphenated_env_cannot_collide_with_a_dimension(): void
    {
        $hyphenatedEnv = new MergedConfigCache($this->cacheDir, 'prod-eu', '/app/root');
        $envPlusRegion = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu']);

        self::assertNotSame($hyphenatedEnv->filename(), $envPlusRegion->filename());
    }

    /**
     * Order is meaning: region eu / tenant acme is not tenant eu / region acme.
     */
    public function test_the_order_of_the_dimensions_is_part_of_the_identity(): void
    {
        $one = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu', 'acme']);
        $two = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['acme', 'eu']);

        self::assertNotSame($one->filename(), $two->filename());
    }

    /**
     * Clearing has to reap every tuple of this app, not just the one the
     * current process happens to be. A region left behind is a stale answer
     * the next deploy of that region reads back.
     */
    public function test_clearing_reaps_every_dimension_tuple_of_this_app(): void
    {
        $eu = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu']);
        $us = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['us']);
        $eu->writeTrusted(['k' => 'eu']);
        $us->writeTrusted(['k' => 'us']);

        $eu->clear();

        self::assertFalse($eu->exists());
        self::assertFalse($us->exists(), "the other region's tuple was left behind");
    }

    /**
     * ...but not another application's, which is what the app hash is for.
     */
    public function test_clearing_leaves_another_apps_cache_alone(): void
    {
        $mine = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu']);
        $theirs = new MergedConfigCache($this->cacheDir, 'prod', '/other/root', ['eu']);
        $mine->writeTrusted(['k' => 'mine']);
        $theirs->writeTrusted(['k' => 'theirs']);

        $mine->clear();

        self::assertFalse($mine->exists());
        self::assertTrue($theirs->exists());
    }

    /**
     * The reap is anchored on the app hash, so a cache with no app root has
     * no siblings to speak of and must not sweep the directory.
     */
    public function test_clearing_an_unscoped_cache_reaps_no_siblings(): void
    {
        $scoped = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu']);
        $scoped->writeTrusted(['k' => 'v']);

        (new MergedConfigCache($this->cacheDir, 'prod'))->clear();

        self::assertTrue($scoped->exists());
    }

    /**
     * And it only reaps merged-config caches: a neighbour in the same
     * directory whose name happens to start the same way is not this class's
     * to delete.
     */
    public function test_clearing_leaves_a_non_cache_neighbour_alone(): void
    {
        $cache = new MergedConfigCache($this->cacheDir, 'prod', '/app/root', ['eu']);
        $cache->writeTrusted(['k' => 'v']);

        $stem = substr($cache->filename(), 0, -strlen(MergedConfigCache::FILENAME_EXTENSION));
        $neighbour = $stem . '-notes.txt';
        file_put_contents($neighbour, 'not a cache');

        $cache->clear();

        self::assertFileExists($neighbour);
        unlink($neighbour);
    }

    public function test_different_envs_produce_isolated_cache_files(): void
    {
        $prod = new MergedConfigCache($this->cacheDir, 'prod');
        $dev = new MergedConfigCache($this->cacheDir, 'dev');

        $prod->writeTrusted(['app' => 'prod']);
        $dev->writeTrusted(['app' => 'dev']);

        self::assertSame(['app' => 'prod'], $prod->load());
        self::assertSame(['app' => 'dev'], $dev->load());
    }

    public function test_different_app_roots_produce_isolated_cache_files_in_a_shared_dir(): void
    {
        $appA = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');
        $appB = new MergedConfigCache($this->cacheDir, '', '/srv/app-b');

        $appA->writeTrusted(['app' => 'a']);
        $appB->writeTrusted(['app' => 'b']);

        self::assertNotSame($appA->filename(), $appB->filename());
        self::assertSame(['app' => 'a'], $appA->load());
        self::assertSame(['app' => 'b'], $appB->load());
    }

    public function test_same_app_root_produces_a_stable_filename(): void
    {
        $first = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');
        $second = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');

        self::assertSame($first->filename(), $second->filename());
    }

    /**
     * A file written before sources were recorded is never read, so nothing
     * would ever replace it: clearing is the one way it goes.
     */
    public function test_clear_removes_a_file_written_before_sources_were_recorded(): void
    {
        $cache = new MergedConfigCache($this->cacheDir, 'prod', '/srv/app');
        $cache->writeTrusted(['current' => true]);

        $earlier = str_replace('gacela-merged-config-v2-', 'gacela-merged-config-', $cache->filename());
        file_put_contents($earlier, "<?php return ['stale' => true];");

        $cache->clear();

        self::assertFileDoesNotExist($earlier);
        self::assertFileDoesNotExist($cache->filename());
    }

    public function test_app_scoped_filename_keeps_env_suffix(): void
    {
        $cache = new MergedConfigCache($this->cacheDir, 'prod', '/srv/app-a');

        self::assertStringEndsWith('-prod' . MergedConfigCache::FILENAME_EXTENSION, $cache->filename());
        self::assertStringContainsString(MergedConfigCache::FILENAME_PREFIX . '-', $cache->filename());
    }

    public function test_clear_also_removes_a_legacy_unscoped_cache_file(): void
    {
        $legacy = new MergedConfigCache($this->cacheDir);
        $legacy->writeTrusted(['stale' => 'legacy']);

        $scoped = new MergedConfigCache($this->cacheDir, '', '/srv/app-a');
        $scoped->writeTrusted(['fresh' => 'scoped']);

        $scoped->clear();

        self::assertFalse($scoped->exists());
        self::assertFalse($legacy->exists());
    }

    public function test_write_creates_cache_directory_when_missing(): void
    {
        $cache = new MergedConfigCache($this->cacheDir);

        $cache->writeTrusted(['key' => 'value']);

        self::assertDirectoryExists($this->cacheDir);
    }

    private function removeCacheDirIfExists(): void
    {
        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->cacheDir);
    }
}
