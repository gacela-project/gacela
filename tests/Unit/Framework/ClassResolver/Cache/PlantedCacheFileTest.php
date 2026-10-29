<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\ScopedCache;
use Gacela\Framework\Cache\WritableDirectory;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\Gacela;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

use function function_exists;

final class PlantedCacheFileTest extends TestCase
{
    public function test_membership_reads_do_not_execute_an_untrusted_file(): void
    {
        $directory = $this->unsafeCacheDirectory();
        $cache = new MembershipCache($directory, '/app');
        $marker = $directory . DIRECTORY_SEPARATOR . 'executed';
        file_put_contents($cache->path(), '<?php file_put_contents(' . var_export($marker, true) . ', "yes"); return ["plugins" => []];');
        try {
            self::assertNull($cache->read());
            self::assertFileDoesNotExist($marker);
        } finally {
            chmod($directory, 0700);
            DirectoryUtil::removeDir($directory);
        }
    }

    public function test_scoped_graph_reads_do_not_execute_an_untrusted_file(): void
    {
        $directory = $this->unsafeCacheDirectory();
        $marker = $directory . DIRECTORY_SEPARATOR . 'executed';
        $file = $directory . DIRECTORY_SEPARATOR . '.gacela-scoped-cache-graph.php';
        file_put_contents($file, '<?php file_put_contents(' . var_export($marker, true) . ', "yes"); return [];');
        try {
            new ScopedCache(new FileCache($directory));
            self::assertFileDoesNotExist($marker);
        } finally {
            WritableDirectory::resetCache();
            chmod($directory, 0700);
            DirectoryUtil::removeDir($directory);
        }
    }

    public function test_a_writable_cache_file_is_rejected_in_a_sticky_directory(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions are required.');
        }

        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-writable-' . uniqid('', true);
        mkdir($directory);
        chmod($directory, 01777);
        $file = $directory . DIRECTORY_SEPARATOR . 'cache.php';
        file_put_contents($file, '<?php return ["planted" => "X"];');
        chmod($file, 0666);
        try {
            self::assertNull(FileCache::readArray($file));
        } finally {
            chmod($directory, 0700);
            DirectoryUtil::removeDir($directory);
        }
    }

    public function test_a_private_cache_file_is_readable_in_a_sticky_directory(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions are required.');
        }

        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-sticky-' . uniqid('', true);
        mkdir($directory);
        chmod($directory, 01777);
        $file = $directory . DIRECTORY_SEPARATOR . 'cache.php';
        try {
            self::assertTrue(FileCache::writeAtomically($file, ['safe' => 'Value']));
            self::assertSame(['safe' => 'Value'], FileCache::readArray($file));
        } finally {
            WritableDirectory::resetCache();
            chmod($directory, 0700);
            DirectoryUtil::removeDir($directory);
        }
    }

    public function test_writes_keep_cache_files_private_with_a_permissive_umask(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions are required.');
        }

        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-mode-' . uniqid('', true);
        mkdir($directory);
        $file = $directory . DIRECTORY_SEPARATOR . 'cache.php';
        $previous = umask(0000);
        try {
            self::assertTrue(FileCache::writeAtomically($file, ['safe' => 'Value']));
            self::assertSame(0600, fileperms($file) & 0777);
        } finally {
            umask($previous);
            WritableDirectory::resetCache();
            DirectoryUtil::removeDir($directory);
        }
    }

    public function test_a_directory_is_rejected_before_loading(): void
    {
        self::assertFalse(FileCache::isSafeToRead(__DIR__));
    }

    public function test_a_symlink_to_a_private_cache_file_is_rejected(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-symlink-' . uniqid('', true);
        mkdir($directory);
        $file = $directory . DIRECTORY_SEPARATOR . 'cache.php';
        $link = $directory . DIRECTORY_SEPARATOR . 'link.php';
        try {
            self::assertTrue(FileCache::writeAtomically($file, ['safe' => 'Value']));
            if (!@symlink($file, $link)) {
                self::markTestSkipped('Creating symbolic links is unavailable.');
            }

            self::assertNull(FileCache::readArray($link));
            self::assertSame(['safe' => 'Value'], FileCache::readArray($file));
        } finally {
            WritableDirectory::resetCache();
            DirectoryUtil::removeDir($directory);
        }
    }

    public function test_a_foreign_owned_file_is_rejected_before_loading(): void
    {
        if (!function_exists('posix_geteuid') || !is_file('/etc/hosts') || fileowner('/etc/hosts') === posix_geteuid()) {
            self::markTestSkipped('A foreign-owned file is required.');
        }

        self::assertFalse(FileCache::isSafeToRead('/etc/hosts'));
    }

    public function test_bootstrap_does_not_execute_an_untrusted_container_plan_file(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions are required.');
        }

        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-plans-' . uniqid('', true);
        $directory = $root . DIRECTORY_SEPARATOR . 'cache';
        mkdir($directory, 0777, true);
        chmod($directory, 0777);
        $file = AbstractPhpFileCache::absoluteFilename($directory, 'gacela-container-plans.php', $root);
        $marker = $root . DIRECTORY_SEPARATOR . 'executed';
        file_put_contents($file, '<?php file_put_contents(' . var_export($marker, true) . ', "yes"); return [];');
        Gacela::resetCache();
        try {
            Gacela::bootstrap($root, static fn (GacelaConfig $config): \Gacela\Framework\Bootstrap\GacelaConfig => $config->enableFileCache($directory));
            self::assertFileDoesNotExist($marker);
        } finally {
            Gacela::resetCache();
            chmod($directory, 0700);
            DirectoryUtil::removeDir($root);
        }
    }

    public function test_a_cache_in_an_unprotected_shared_directory_is_never_executed(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions are required.');
        }

        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-planted-' . uniqid('', true);
        mkdir($directory);
        chmod($directory, 0777);
        $file = TestPhpFileCache::absoluteFilename($directory, TestPhpFileCache::FILENAME, '');
        $marker = $directory . DIRECTORY_SEPARATOR . 'executed';
        file_put_contents($file, '<?php file_put_contents(' . var_export($marker, true) . ', "yes"); return ["planted" => "X"];');
        AbstractPhpFileCache::resetCache();
        try {
            $cache = new TestPhpFileCache($directory);
            self::assertSame([], $cache->getAll());
            self::assertFileDoesNotExist($marker);
            $cache->put('safe', 'Value');
            self::assertSame(['safe' => 'Value'], require $file);
        } finally {
            AbstractPhpFileCache::resetCache();
            WritableDirectory::resetCache();
            chmod($directory, 0700);
            DirectoryUtil::removeDir($directory);
        }
    }

    private function unsafeCacheDirectory(): string
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions are required.');
        }

        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-untrusted-' . uniqid('', true);
        mkdir($directory);
        chmod($directory, 0777);

        return $directory;
    }

}
