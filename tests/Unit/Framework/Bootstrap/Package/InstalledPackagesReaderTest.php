<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Package;

use Gacela\Framework\Bootstrap\Package\InstalledPackagesReader;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function touch;
use function unlink;

final class InstalledPackagesReaderTest extends TestCase
{
    /** Pinned so a fingerprint comparison is about the half the test is about. */
    private const int FIXED_MTIME = 1_600_000_000;

    private string $appRoot = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-installed-reader-' . bin2hex(random_bytes(4));
        mkdir($this->appRoot, 0o777, true);
    }

    /**
     * Names every file and directory it created, in creation order reversed.
     * Nothing here is derived from a glob or from configuration.
     */
    protected function tearDown(): void
    {
        $installedJson = $this->appRoot . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';

        foreach ([$installedJson] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ([dirname($installedJson), dirname($installedJson, 2), $this->appRoot] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function test_it_names_the_file_composer_writes(): void
    {
        $reader = new InstalledPackagesReader($this->appRoot);

        self::assertSame(
            $this->appRoot . DIRECTORY_SEPARATOR . 'vendor'
                . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json',
            $reader->path(),
        );
    }

    public function test_an_absent_file_reads_as_nothing_to_read(): void
    {
        $reader = new InstalledPackagesReader($this->appRoot);

        self::assertNull($reader->read());
        self::assertNull($reader->fingerprint());
    }

    public function test_it_reads_the_packages_key_composer_2_writes(): void
    {
        $this->writeInstalledJson([
            'packages' => [
                ['name' => 'acme/first'],
                ['name' => 'acme/second'],
            ],
            'dev' => true,
        ]);

        self::assertSame(
            [['name' => 'acme/first'], ['name' => 'acme/second']],
            (new InstalledPackagesReader($this->appRoot))->read(),
        );
    }

    /**
     * Composer 1 wrote the list at the top level. Still read, because what is on
     * disk is whatever last wrote it.
     */
    public function test_it_reads_a_top_level_list(): void
    {
        $this->writeInstalledJson([['name' => 'acme/only']]);

        self::assertSame(
            [['name' => 'acme/only']],
            (new InstalledPackagesReader($this->appRoot))->read(),
        );
    }

    /**
     * Keys are dropped, not preserved: every caller iterates, and a map with
     * numeric-string keys is not the `list` the annotation promises.
     */
    public function test_it_returns_a_list_whatever_the_keys_were(): void
    {
        $this->writeInstalledJson(['packages' => ['7' => ['name' => 'acme/keyed']]]);

        self::assertSame(
            [['name' => 'acme/keyed']],
            (new InstalledPackagesReader($this->appRoot))->read(),
        );
    }

    public function test_malformed_json_reads_as_nothing_to_read(): void
    {
        $this->writeRaw('{not json');

        self::assertNull((new InstalledPackagesReader($this->appRoot))->read());
    }

    public function test_a_packages_key_that_is_not_a_list_reads_as_nothing_to_read(): void
    {
        $this->writeInstalledJson(['packages' => 'acme/first']);

        self::assertNull((new InstalledPackagesReader($this->appRoot))->read());
    }

    public function test_the_fingerprint_answers_for_a_file_that_exists(): void
    {
        $this->writeInstalledJson(['packages' => [['name' => 'acme/first']]]);

        $reader = new InstalledPackagesReader($this->appRoot);
        $fingerprint = $reader->fingerprint();

        self::assertNotNull($fingerprint);
        self::assertSame($fingerprint, $reader->fingerprint(), 'the same file fingerprints the same way twice');
    }

    /**
     * The whole point: a reinstall has to invalidate the cache keyed on this.
     * Both halves of the fingerprint are pinned, one per test, because a
     * fingerprint that quietly dropped one would still answer this one.
     */
    public function test_a_file_of_a_different_size_fingerprints_differently(): void
    {
        $this->writeInstalledJson(['packages' => [['name' => 'acme/first']]]);
        touch($this->installedJsonPath(), self::FIXED_MTIME);
        $before = (new InstalledPackagesReader($this->appRoot))->fingerprint();

        $this->writeInstalledJson(['packages' => [['name' => 'acme/first'], ['name' => 'acme/second']]]);
        touch($this->installedJsonPath(), self::FIXED_MTIME);

        self::assertNotSame($before, (new InstalledPackagesReader($this->appRoot))->fingerprint());
    }

    /**
     * `composer install` rewriting the same set of packages leaves a file of the
     * same size. Only the modification time says it was written again.
     */
    public function test_a_rewritten_file_of_the_same_size_fingerprints_differently(): void
    {
        $this->writeInstalledJson(['packages' => [['name' => 'acme/first']]]);
        touch($this->installedJsonPath(), self::FIXED_MTIME);
        $before = (new InstalledPackagesReader($this->appRoot))->fingerprint();

        touch($this->installedJsonPath(), self::FIXED_MTIME + 60);

        self::assertNotSame($before, (new InstalledPackagesReader($this->appRoot))->fingerprint());
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private function writeInstalledJson(array $decoded): void
    {
        $this->writeRaw((string)json_encode($decoded));
    }

    private function writeRaw(string $contents): void
    {
        $path = $this->installedJsonPath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        file_put_contents($path, $contents);
    }

    private function installedJsonPath(): string
    {
        return $this->appRoot . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
    }
}
