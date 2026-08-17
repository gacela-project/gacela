<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Package;

use Gacela\Framework\Bootstrap\Package\PackageConfigCache;
use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

final class PackageConfigCacheTest extends TestCase
{
    private const string FINGERPRINT = '1600000000-4096';

    private string $cacheDir = '';

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-package-cache-' . bin2hex(random_bytes(4));
        mkdir($this->cacheDir, 0o777, true);
    }

    /**
     * Names the one file this can create, and the one directory.
     */
    protected function tearDown(): void
    {
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . PackageConfigCache::FILENAME;

        if (is_file($file)) {
            unlink($file);
        }

        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function test_the_file_sits_in_the_cache_directory_under_a_stable_name(): void
    {
        self::assertSame(
            $this->cacheDir . DIRECTORY_SEPARATOR . 'gacela-discovered-packages.php',
            $this->cache()->path(),
        );
    }

    public function test_nothing_written_is_nothing_to_read(): void
    {
        self::assertNull($this->cache()->read(self::FINGERPRINT));
    }

    public function test_what_was_written_comes_back_in_order(): void
    {
        $declarations = [
            new PackageConfigDeclaration('acme/first', 'config/gacela.php', '/vendor/acme/first/config/gacela.php'),
            new PackageConfigDeclaration('acme/second', 'gacela.php', '/vendor/acme/second/gacela.php'),
        ];

        $this->cache()->write(self::FINGERPRINT, $declarations);

        self::assertEquals($declarations, $this->cache()->read(self::FINGERPRINT));
    }

    /**
     * The whole point of the key: a `composer install` moves the fingerprint and
     * the previous answer stops being an answer.
     */
    public function test_an_entry_written_for_another_fingerprint_is_not_read(): void
    {
        $this->cache()->write(self::FINGERPRINT, [
            new PackageConfigDeclaration('acme/first', 'config/gacela.php', '/vendor/acme/first/config/gacela.php'),
        ]);

        self::assertNull($this->cache()->read('1600000001-4096'));
    }

    public function test_an_empty_list_is_an_answer_and_not_a_miss(): void
    {
        $this->cache()->write(self::FINGERPRINT, []);

        self::assertSame([], $this->cache()->read(self::FINGERPRINT));
    }

    /**
     * @param mixed $payload what the cache file returns
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusablePayloads')]
    public function test_an_unusable_entry_is_discarded_rather_than_repaired(mixed $payload): void
    {
        $this->writeRaw($payload);

        self::assertNull($this->cache()->read(self::FINGERPRINT));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusablePayloads(): iterable
    {
        yield 'not an array' => ['nope'];
        yield 'no fingerprint' => [['packages' => []]];
        yield 'fingerprint is not a string' => [['fingerprint' => 1_600_000_000, 'packages' => []]];
        yield 'no packages' => [['fingerprint' => self::FINGERPRINT]];
        yield 'packages is not an array' => [['fingerprint' => self::FINGERPRINT, 'packages' => 'acme/first']];
        yield 'a row is not an array' => [['fingerprint' => self::FINGERPRINT, 'packages' => ['acme/first']]];
        yield 'a row is missing a field' => [['fingerprint' => self::FINGERPRINT, 'packages' => [['name' => 'acme/first']]]];
    }

    /**
     * One unreadable row makes the whole entry untrustworthy: booting with some
     * of an application's packages is worse than reading the manifest again.
     */
    public function test_one_unreadable_row_discards_the_whole_entry(): void
    {
        $this->writeRaw([
            'fingerprint' => self::FINGERPRINT,
            'packages' => [
                ['name' => 'acme/first', 'declaredPath' => 'config/gacela.php', 'configFile' => '/vendor/acme/first/config/gacela.php'],
                ['name' => 'acme/second'],
            ],
        ]);

        self::assertNull($this->cache()->read(self::FINGERPRINT));
    }

    private function cache(): PackageConfigCache
    {
        return new PackageConfigCache($this->cacheDir);
    }

    private function writeRaw(mixed $payload): void
    {
        file_put_contents(
            $this->cacheDir . DIRECTORY_SEPARATOR . PackageConfigCache::FILENAME,
            sprintf('<?php return %s;', var_export($payload, true)),
        );
    }
}
