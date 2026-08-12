<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\PackageManifest;

use Gacela\Console\Domain\PackageManifest\ComposerPackageFinder;
use Gacela\Console\Domain\PackageManifest\NamespacePackageMap;
use Gacela\Console\Domain\PackageManifest\PackageManifestChecker;
use Gacela\Console\Domain\PackageManifest\UndeclaredImport;
use PHPUnit\Framework\TestCase;

use stdClass;

use function array_map;
use function bin2hex;
use function dirname;
use function is_dir;
use function is_string;
use function json_encode;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * The fixture repository is built under a temp directory rather than committed.
 *
 * ComposerPackageFinder finds every `composer.json` outside `vendor`, so a
 * fixture manifest living under `tests/` would be picked up by this
 * repository's own `doctor` run and reported as a real package.
 */
final class PackageManifestCheckerTest extends TestCase
{
    private string $repoDir = '';

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->repoDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-manifests-' . bin2hex(random_bytes(4));
        mkdir($this->repoDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Only the files this test wrote, each asserted to sit under the temp
        // directory this test created.
        foreach (array_reverse($this->createdFiles) as $file) {
            self::assertStringStartsWith($this->repoDir . DIRECTORY_SEPARATOR, $file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->removeEmptyTree($this->repoDir);
    }

    /**
     * The bug this check exists for, reproduced: a sub-package importing the
     * framework while declaring only the container. Inside the monorepo the
     * root autoloader supplies it; installed alone it fatals.
     */
    public function test_an_import_the_manifest_never_mentions_is_reported(): void
    {
        $this->writePackage('bridge', 'acme/bridge', ['acme/container' => '^1.0'], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);

        $findings = $this->check();

        self::assertCount(1, $findings);
        self::assertSame('acme/bridge', $findings[0]->package);
        self::assertSame('Acme\Framework\Kernel', $findings[0]->import);
        self::assertSame('acme/framework', $findings[0]->providedBy);
    }

    public function test_an_import_the_manifest_requires_is_not_reported(): void
    {
        $this->writePackage('bridge', 'acme/bridge', ['acme/framework' => '^1.0'], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);

        self::assertSame([], $this->check());
    }

    /**
     * Every section counts, because a package shipped as a phar declares no
     * autoload prefix and its classes get attributed to whichever sibling
     * publishes the namespace -- so demanding a particular section would name
     * the wrong package to add.
     */
    public function test_a_mention_in_require_dev_or_suggest_is_enough(): void
    {
        $this->writePackage('bridge', 'acme/bridge', [], 'Acme\Bridge\\', ['acme/framework' => '^1.0']);
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);

        self::assertSame([], $this->check());
    }

    public function test_a_package_importing_its_own_namespace_is_not_reported(): void
    {
        $this->writePackage('bridge', 'acme/bridge', [], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Bridge\Other']);

        self::assertSame([], $this->check());
    }

    /**
     * A monorepo root commonly autoloads its sub-packages so one install serves
     * development. Holding the root to their imports would name a requirement
     * in the wrong manifest.
     */
    public function test_a_nested_packages_files_are_not_charged_to_the_root(): void
    {
        // The shape a monorepo actually has: the root autoloads the sub-package
        // too, so that one `composer install` serves development.
        $this->writeManifest('composer.json', 'acme/root', [], [
            'Acme\\' => 'src',
            'Acme\Bridge\\' => 'bridge/src',
        ]);
        $this->writeClass('src/RootThing.php', 'Acme', []);
        $this->writePackage('bridge', 'acme/bridge', ['acme/framework' => '^1.0'], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);

        // Both manifests declare `Acme\Bridge\`; the bridge owns it, and it
        // already declares what those files import.
        self::assertSame([], $this->check());
    }

    /**
     * The same overlap, with the sub-package's manifest incomplete: the finding
     * has to name the sub-package, because that is the manifest to fix and the
     * one that gets published on its own.
     */
    public function test_an_overlapping_prefix_reports_the_deeper_package(): void
    {
        $this->writeManifest('composer.json', 'acme/root', ['acme/framework' => '^1.0'], [
            'Acme\\' => 'src',
            'Acme\Bridge\\' => 'bridge/src',
        ]);
        $this->writeClass('src/RootThing.php', 'Acme', []);
        $this->writePackage('bridge', 'acme/bridge', [], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);

        $findings = $this->check();

        self::assertCount(1, $findings);
        self::assertSame('acme/bridge', $findings[0]->package);
    }

    /**
     * A self-import is skipped, not a reason to stop reading the file: the
     * imports after it still have to be checked.
     */
    public function test_a_self_import_does_not_stop_the_rest_of_the_file(): void
    {
        $this->writePackage('bridge', 'acme/bridge', [], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', [
            'Acme\Bridge\Other',
            'Acme\Framework\Kernel',
        ]);

        $findings = $this->check();

        self::assertCount(1, $findings);
        self::assertSame('acme/framework', $findings[0]->providedBy);
    }

    /**
     * A declared import is skipped, not a reason to stop reading the file.
     */
    public function test_a_declared_import_does_not_stop_the_rest_of_the_file(): void
    {
        $this->writePackage('bridge', 'acme/bridge', ['acme/container' => '^1.0'], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', [
            'Acme\Container\Box',
            'Acme\Framework\Kernel',
        ]);

        $findings = $this->check();

        self::assertCount(1, $findings);
        self::assertSame('acme/framework', $findings[0]->providedBy);
    }

    /**
     * One finding per missing package, naming the first import that needed it.
     */
    public function test_one_missing_package_is_reported_once(): void
    {
        $this->writePackage('bridge', 'acme/bridge', [], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/A.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);
        $this->writeClass('bridge/src/B.php', 'Acme\Bridge', ['Acme\Framework\Other']);

        $findings = $this->check();

        self::assertCount(1, $findings);
        self::assertSame('Acme\Framework\Kernel', $findings[0]->import);
    }

    public function test_each_missing_package_gets_its_own_finding(): void
    {
        $this->writePackage('bridge', 'acme/bridge', [], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/A.php', 'Acme\Bridge', ['Acme\Framework\Kernel', 'Acme\Container\Box']);

        // Declaration order, which is deterministic: files are walked sorted and
        // imports keep the order the source wrote them.
        self::assertSame(
            ['acme/framework', 'acme/container'],
            array_map(static fn (UndeclaredImport $f): string => $f->providedBy, $this->check()),
        );
    }

    /**
     * Two packages at the same depth declaring distinct prefixes each own
     * theirs, so neither silences the other.
     */
    public function test_siblings_each_own_their_own_prefix(): void
    {
        $this->writePackage('bridge', 'acme/bridge', [], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/A.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);
        $this->writePackage('extra', 'acme/extra', [], 'Acme\Extra\\');
        $this->writeClass('extra/src/B.php', 'Acme\Extra', ['Acme\Container\Box']);

        self::assertSame(
            ['acme/bridge', 'acme/extra'],
            array_map(static fn (UndeclaredImport $f): string => $f->package, $this->check()),
        );
    }

    /**
     * Two packages at the same depth declaring one prefix have a genuine
     * conflict, and neither is the obvious owner. Both keep checking their own
     * files, because disowning it on both sides would silently check nothing.
     */
    public function test_two_packages_at_the_same_depth_both_keep_their_prefix(): void
    {
        $this->writePackage('one', 'acme/one', [], 'Acme\Shared\\');
        $this->writeClass('one/src/A.php', 'Acme\Shared', ['Acme\Framework\Kernel']);
        $this->writePackage('two', 'acme/two', [], 'Acme\Shared\\');
        $this->writeClass('two/src/B.php', 'Acme\Shared', ['Acme\Framework\Kernel']);

        self::assertSame(
            ['acme/one', 'acme/two'],
            array_map(static fn (UndeclaredImport $f): string => $f->package, $this->check()),
        );
    }

    /**
     * A psr-4 directory may be written with a trailing separator; it names the
     * same directory either way.
     */
    public function test_a_trailing_separator_on_the_autoload_directory_is_tolerated(): void
    {
        $this->writeManifest('bridge/composer.json', 'acme/bridge', [], ['Acme\Bridge\\' => 'src/']);
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);

        $findings = $this->check();

        self::assertCount(1, $findings);
        self::assertSame('acme/framework', $findings[0]->providedBy);
    }

    /**
     * A prefix another package owns is skipped, not a reason to stop: the
     * prefixes listed after it still belong to this package.
     */
    public function test_a_disowned_prefix_does_not_stop_the_later_ones(): void
    {
        // The disowned prefix is declared first, so skipping it and stopping at
        // it are different outcomes.
        $this->writeManifest('composer.json', 'acme/root', [], [
            'Acme\Bridge\\' => 'bridge/src',
            'Acme\\' => 'src',
        ]);
        $this->writeClass('src/RootThing.php', 'Acme', ['Acme\Framework\Kernel']);
        $this->writePackage('bridge', 'acme/bridge', ['acme/framework' => '^1.0'], 'Acme\Bridge\\');
        $this->writeClass('bridge/src/Bootstrapper.php', 'Acme\Bridge', ['Acme\Framework\Kernel']);

        $findings = $this->check();

        self::assertCount(1, $findings);
        self::assertSame('acme/root', $findings[0]->package);
    }

    /**
     * Ownership is decided against every other package, not just up to the
     * first one that shares no prefix.
     */
    public function test_ownership_considers_every_other_package(): void
    {
        $this->writeManifest('composer.json', 'acme/root', [], ['Acme\Deep\\' => 'zz-deep/src']);
        // Sorted before the deep package, and sharing no prefix with the root,
        // so stopping at it rather than skipping it is a different outcome.
        $this->writePackage('aa-unrelated', 'acme/unrelated', [], 'Acme\Unrelated\\');
        $this->writeClass('aa-unrelated/src/U.php', 'Acme\Unrelated', []);
        $this->writePackage('zz-deep', 'acme/deep', ['acme/framework' => '^1.0'], 'Acme\Deep\\');
        $this->writeClass('zz-deep/src/D.php', 'Acme\Deep', ['Acme\Framework\Kernel']);

        // acme/deep is the deepest claimant of `Acme\Deep\` and declares what it
        // imports, so nothing is reported against the root.
        self::assertSame([], $this->check());
    }

    /**
     * @return list<UndeclaredImport>
     */
    private function check(): array
    {
        $packages = (new ComposerPackageFinder())->findIn($this->repoDir);

        $installed = [
            ['name' => 'acme/framework', 'autoload' => ['psr-4' => ['Acme\Framework\\' => 'src']]],
            ['name' => 'acme/container', 'autoload' => ['psr-4' => ['Acme\Container\\' => 'src']]],
        ];

        return (new PackageManifestChecker())->check($packages, NamespacePackageMap::from($packages, $installed));
    }

    /**
     * @param array<string, string> $require
     * @param array<string, string> $requireDev
     */
    private function writePackage(
        string $relativeDir,
        string $name,
        array $require,
        string $prefix,
        array $requireDev = [],
    ): void {
        $this->writeManifest(
            $relativeDir === '.' ? 'composer.json' : $relativeDir . '/composer.json',
            $name,
            $require,
            [$prefix => 'src'],
            $requireDev,
        );
    }

    /**
     * @param array<string, string> $require
     * @param array<string, string> $psr4
     * @param array<string, string> $requireDev
     */
    private function writeManifest(
        string $relativePath,
        string $name,
        array $require,
        array $psr4,
        array $requireDev = [],
    ): void {
        $this->write($relativePath, (string)json_encode([
            'name' => $name,
            'require' => $require === [] ? new stdClass() : $require,
            'require-dev' => $requireDev === [] ? new stdClass() : $requireDev,
            'autoload' => ['psr-4' => $psr4],
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
