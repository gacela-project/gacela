<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\PackageManifestCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;

use stdClass;

use function bin2hex;
use function dirname;
use function is_dir;
use function is_string;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class PackageManifestCheckTest extends TestCase
{
    private string $repoDir = '';

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->repoDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-manifest-check-' . bin2hex(random_bytes(4));
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

    public function test_a_project_with_no_named_package_passes(): void
    {
        $result = (new PackageManifestCheck($this->repoDir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertStringContainsString('no named composer package', $result->details[0]);
    }

    /**
     * Without installed.json an import cannot be attributed to a package, and
     * guessing would name the wrong manifest to fix.
     */
    public function test_without_installed_json_the_check_says_so_rather_than_guessing(): void
    {
        $this->writeManifest('composer.json', 'acme/root', ['psr-4' => ['Acme\\' => 'src']]);

        $result = (new PackageManifestCheck($this->repoDir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertStringContainsString('installed.json', $result->details[0]);
    }

    public function test_a_package_declaring_everything_it_imports_passes(): void
    {
        $this->writeManifest('composer.json', 'acme/root', ['psr-4' => ['Acme\\' => 'src']], ['acme/framework' => '^1.0']);
        $this->writeClass('src/Thing.php', 'Acme', ['Acme\Framework\Kernel']);
        $this->writeInstalled();

        $result = (new PackageManifestCheck($this->repoDir))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertStringContainsString('1 package(s)', $result->details[0]);
    }

    public function test_an_undeclared_import_warns_and_names_both_sides(): void
    {
        $this->writeManifest('composer.json', 'acme/root', ['psr-4' => ['Acme\\' => 'src']]);
        $this->writeClass('src/Thing.php', 'Acme', ['Acme\Framework\Kernel']);
        $this->writeInstalled();

        $result = (new PackageManifestCheck($this->repoDir))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('acme/root', $result->details[0]);
        self::assertStringContainsString('Acme\Framework\Kernel', $result->details[0]);
        self::assertStringContainsString('acme/framework', $result->details[0]);
        self::assertStringContainsString('suggest', $result->remediation);
    }

    private function writeInstalled(): void
    {
        $this->write('vendor/composer/installed.json', (string)json_encode([
            'packages' => [
                ['name' => 'acme/framework', 'autoload' => ['psr-4' => ['Acme\Framework\\' => 'src']]],
            ],
        ]));
    }

    /**
     * @param array<string, array<string, string>> $autoload
     * @param array<string, string> $require
     */
    private function writeManifest(string $relativePath, string $name, array $autoload = [], array $require = []): void
    {
        $this->write($relativePath, (string)json_encode([
            'name' => $name,
            'autoload' => $autoload === [] ? new stdClass() : $autoload,
            'require' => $require === [] ? new stdClass() : $require,
        ]));
    }

    /**
     * @param list<string> $imports
     */
    private function writeClass(string $relativePath, string $namespace, array $imports): void
    {
        $useLines = implode("\n", array_map(static fn (string $i): string => 'use ' . $i . ';', $imports));

        $this->write($relativePath, "<?php\n\nnamespace {$namespace};\n\n{$useLines}\n\nfinal class Generated {}\n");
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
