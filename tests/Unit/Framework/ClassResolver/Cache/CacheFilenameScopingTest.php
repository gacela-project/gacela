<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use PHPUnit\Framework\TestCase;

use function basename;
use function dirname;

use const DIRECTORY_SEPARATOR;

/**
 * The cache directory defaults to the system temp dir, so it is shared by every
 * application on the machine. The app-root hash in the filename is the only
 * thing stopping two of them reading each other's resolved classes.
 *
 * These pin the shape rather than the digest: the exact hash is an
 * implementation detail, but its presence, its length and what it is derived
 * *from* are the behaviour.
 */
final class CacheFilenameScopingTest extends TestCase
{
    private const DIR = '/tmp/cache-dir';

    public function test_the_file_lives_in_the_cache_dir_and_keeps_its_base_name(): void
    {
        $path = AbstractPhpFileCache::absoluteFilename(self::DIR, ClassNamePhpCache::FILENAME, '/srv/app');

        self::assertSame(self::DIR, dirname($path));
        self::assertMatchesRegularExpression('/^gacela-class-names-[0-9a-f]{12}\.php$/', basename($path));
    }

    /**
     * Without an app root there is nothing to scope by, and the bare name is
     * what every version before this wrote.
     */
    public function test_no_app_root_yields_the_unscoped_name(): void
    {
        self::assertSame(
            self::DIR . DIRECTORY_SEPARATOR . ClassNamePhpCache::FILENAME,
            AbstractPhpFileCache::absoluteFilename(self::DIR, ClassNamePhpCache::FILENAME, ''),
        );
    }

    public function test_the_same_app_root_always_yields_the_same_file(): void
    {
        self::assertSame(
            AbstractPhpFileCache::absoluteFilename(self::DIR, ClassNamePhpCache::FILENAME, '/srv/app'),
            AbstractPhpFileCache::absoluteFilename(self::DIR, ClassNamePhpCache::FILENAME, '/srv/app'),
        );
    }

    /**
     * The whole point: two applications sharing one cache dir must not collide.
     */
    public function test_two_app_roots_never_share_a_file(): void
    {
        self::assertNotSame(
            AbstractPhpFileCache::absoluteFilename(self::DIR, ClassNamePhpCache::FILENAME, '/srv/app-one'),
            AbstractPhpFileCache::absoluteFilename(self::DIR, ClassNamePhpCache::FILENAME, '/srv/app-two'),
        );
    }

    /**
     * The suffix has to come from the app root and nothing else. Derived from
     * the cache dir instead, it would be identical for exactly the applications
     * it is meant to separate -- the ones sharing a directory.
     */
    public function test_the_suffix_follows_the_app_root_not_the_directory(): void
    {
        $here = basename(AbstractPhpFileCache::absoluteFilename('/tmp/one', ClassNamePhpCache::FILENAME, '/srv/app'));
        $there = basename(AbstractPhpFileCache::absoluteFilename('/tmp/two', ClassNamePhpCache::FILENAME, '/srv/app'));

        self::assertSame($here, $there);
    }

    public function test_each_cache_keeps_its_own_name(): void
    {
        $classNames = AbstractPhpFileCache::absoluteFilename(self::DIR, ClassNamePhpCache::FILENAME, '/srv/app');
        $custom = AbstractPhpFileCache::absoluteFilename(self::DIR, 'gacela-custom-services.php', '/srv/app');

        self::assertNotSame($classNames, $custom);
        self::assertMatchesRegularExpression('/^gacela-custom-services-[0-9a-f]{12}\.php$/', basename($custom));
    }
}
