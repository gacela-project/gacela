<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\PackageManifest;

use Gacela\Console\Domain\PackageManifest\ComposerPackage;
use Gacela\Console\Domain\PackageManifest\ComposerPackageFinder;
use PHPUnit\Framework\TestCase;

use function array_map;
use function bin2hex;
use function dirname;
use function is_dir;
use function is_string;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class ComposerPackageFinderTest extends TestCase
{
    private string $repoDir = '';

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->repoDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-finder-' . bin2hex(random_bytes(4));
        mkdir($this->repoDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdFiles) as $file) {
            self::assertStringStartsWith($this->repoDir . DIRECTORY_SEPARATOR, $file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->removeEmptyTree($this->repoDir);
    }

    public function test_a_directory_that_does_not_exist_yields_nothing(): void
    {
        self::assertSame([], (new ComposerPackageFinder())->findIn($this->repoDir . '-missing'));
    }

    public function test_it_finds_the_root_and_every_nested_manifest(): void
    {
        $this->writeManifest('composer.json', 'acme/root');
        $this->writeManifest('bridge/composer.json', 'acme/bridge');

        self::assertSame(['acme/bridge', 'acme/root'], $this->names());
    }

    /**
     * An installed package's manifest describes a decision somebody else made,
     * and reporting on it would be noise nobody here can act on.
     */
    public function test_it_skips_vendor(): void
    {
        $this->writeManifest('composer.json', 'acme/root');
        $this->writeManifest('vendor/other/pkg/composer.json', 'other/pkg');

        self::assertSame(['acme/root'], $this->names());
    }

    public function test_a_manifest_without_a_name_is_skipped(): void
    {
        $this->writeManifest('composer.json', 'acme/root');
        $this->write('tooling/composer.json', '{"require": {"acme/thing": "^1.0"}}');

        self::assertSame(['acme/root'], $this->names());
    }

    public function test_a_manifest_that_is_not_valid_json_is_skipped(): void
    {
        $this->writeManifest('composer.json', 'acme/root');
        $this->write('broken/composer.json', '{not json');

        self::assertSame(['acme/root'], $this->names());
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return array_map(
            static fn (ComposerPackage $package): string => $package->name,
            (new ComposerPackageFinder())->findIn($this->repoDir),
        );
    }

    private function writeManifest(string $relativePath, string $name): void
    {
        $this->write($relativePath, (string)json_encode(['name' => $name]));
    }

    private function write(string $relativePath, string $contents): void
    {
        $absolute = $this->repoDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }

        file_put_contents($absolute, $contents);
        $this->createdFiles[] = $absolute;
    }

    private function removeEmptyTree(string $directory): void
    {
        self::assertStringStartsWith(sys_get_temp_dir() . DIRECTORY_SEPARATOR, $directory);

        foreach ((array)glob($directory . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $child) {
            if (is_string($child)) {
                $this->removeEmptyTree($child);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
