<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Package;

use Gacela\Framework\Bootstrap\Package\InstalledPackagesReader;
use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use Gacela\Framework\Bootstrap\Package\PackageConfigFinder;
use PHPUnit\Framework\TestCase;

use function array_map;
use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function str_replace;
use function sys_get_temp_dir;
use function unlink;

final class PackageConfigFinderTest extends TestCase
{
    private string $appRoot = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-config-finder-' . bin2hex(random_bytes(4));
        mkdir($this->appRoot, 0o777, true);
    }

    /**
     * Names every path it created, in reverse.
     */
    protected function tearDown(): void
    {
        $installedJson = $this->installedJsonPath();

        if (is_file($installedJson)) {
            unlink($installedJson);
        }

        foreach ([dirname($installedJson), dirname($installedJson, 2), $this->appRoot] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function test_without_installed_json_there_is_nothing_to_find(): void
    {
        self::assertSame([], $this->find());
    }

    public function test_only_packages_carrying_the_key_are_considered(): void
    {
        $this->writeInstalled([
            ['name' => 'acme/plain'],
            ['name' => 'acme/other-extra', 'extra' => ['laravel' => ['providers' => []]]],
            ['name' => 'acme/gacela-but-empty', 'extra' => ['gacela' => []]],
            ['name' => 'acme/audit', 'extra' => ['gacela' => ['config' => 'config/gacela.php']]],
        ]);

        self::assertSame(['acme/audit'], $this->names());
    }

    public function test_declarations_keep_composers_installed_order(): void
    {
        $this->writeInstalled([
            $this->entry('acme/first'),
            $this->entry('acme/second'),
            $this->entry('acme/third'),
        ]);

        self::assertSame(['acme/first', 'acme/second', 'acme/third'], $this->names());
    }

    /**
     * Composer writes `install-path` relative to `vendor/composer`, and it is
     * `..`-heavy for anything installed outside the vendor tree.
     */
    public function test_a_relative_install_path_resolves_against_vendor_composer(): void
    {
        $this->writeInstalled([
            ['name' => 'acme/audit', 'install-path' => '../acme/audit', 'extra' => ['gacela' => ['config' => 'config/gacela.php']]],
        ]);

        self::assertSame(
            $this->path($this->appRoot, 'vendor', 'acme', 'audit', 'config', 'gacela.php'),
            $this->find()[0]->configFile,
        );
    }

    public function test_a_relative_install_path_may_climb_out_of_the_vendor_tree(): void
    {
        $this->writeInstalled([
            ['name' => 'acme/audit', 'install-path' => '../../../packages/audit', 'extra' => ['gacela' => ['config' => 'config/gacela.php']]],
        ]);

        self::assertSame(
            $this->path(dirname($this->appRoot), 'packages', 'audit', 'config', 'gacela.php'),
            $this->find()[0]->configFile,
        );
    }

    public function test_an_absolute_install_path_is_taken_as_it_is(): void
    {
        $absolute = $this->path($this->appRoot, 'elsewhere', 'audit');

        $this->writeInstalled([
            ['name' => 'acme/audit', 'install-path' => $absolute, 'extra' => ['gacela' => ['config' => 'config/gacela.php']]],
        ]);

        self::assertSame($absolute . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'gacela.php', $this->find()[0]->configFile);
    }

    /**
     * Composer 1 recorded no install path, and a package installed where its
     * name says it is needs none.
     */
    public function test_without_an_install_path_the_package_name_says_where_it_is(): void
    {
        $this->writeInstalled([$this->entry('acme/audit')]);

        self::assertSame(
            $this->path($this->appRoot, 'vendor', 'acme', 'audit', 'config', 'gacela.php'),
            $this->find()[0]->configFile,
        );
    }

    public function test_the_declared_path_is_kept_as_the_package_wrote_it(): void
    {
        $this->writeInstalled([
            ['name' => 'acme/audit', 'extra' => ['gacela' => ['config' => './config/gacela.php']]],
        ]);

        $declaration = $this->find()[0];

        self::assertSame('./config/gacela.php', $declaration->declaredPath);
        self::assertSame(
            $this->path($this->appRoot, 'vendor', 'acme', 'audit', 'config', 'gacela.php'),
            $declaration->configFile,
            'the resolved path drops the `.` segment the declaration kept',
        );
    }

    /**
     * A manifest is data nobody here owns, so every shape that is not the one
     * expected has to be survivable rather than fatal.
     *
     * @param array<array-key, mixed> $entry
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusableEntries')]
    public function test_an_unusable_entry_is_skipped(array $entry): void
    {
        $this->writeInstalled([$entry, $this->entry('acme/usable')]);

        self::assertSame(['acme/usable'], $this->names());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function unusableEntries(): iterable
    {
        yield 'no name' => [['extra' => ['gacela' => ['config' => 'config/gacela.php']]]];
        yield 'empty name' => [['name' => '', 'extra' => ['gacela' => ['config' => 'config/gacela.php']]]];
        yield 'name is not a string' => [['name' => 42, 'extra' => ['gacela' => ['config' => 'config/gacela.php']]]];
        yield 'extra is not an array' => [['name' => 'acme/x', 'extra' => 'gacela']];
        yield 'gacela is not an array' => [['name' => 'acme/x', 'extra' => ['gacela' => 'config/gacela.php']]];
        yield 'config is not a string' => [['name' => 'acme/x', 'extra' => ['gacela' => ['config' => ['a']]]]];
        yield 'config is empty' => [['name' => 'acme/x', 'extra' => ['gacela' => ['config' => '']]]];
        yield 'not an object at all' => [['acme/x']];
    }

    public function test_an_entry_that_is_not_an_array_is_skipped(): void
    {
        $this->writeRaw((string) json_encode(['packages' => ['acme/audit', $this->entry('acme/usable')]]));

        self::assertSame(['acme/usable'], $this->names());
    }

    /**
     * @return list<PackageConfigDeclaration>
     */
    private function find(): array
    {
        return (new PackageConfigFinder(new InstalledPackagesReader($this->appRoot)))->find();
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return array_map(
            static fn (PackageConfigDeclaration $declaration): string => $declaration->name,
            $this->find(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $name): array
    {
        return ['name' => $name, 'extra' => ['gacela' => ['config' => 'config/gacela.php']]];
    }

    /**
     * @param list<mixed> $packages
     */
    private function writeInstalled(array $packages): void
    {
        $this->writeRaw((string) json_encode(['packages' => $packages]));
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
        return $this->path($this->appRoot, 'vendor', 'composer', 'installed.json');
    }

    private function path(string ...$segments): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, implode('/', $segments));
    }
}
