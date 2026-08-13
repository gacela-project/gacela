<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Preload;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Preload\Preloader;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function bin2hex;
use function count;
use function dirname;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sort;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

final class PreloaderTest extends TestCase
{
    private string $fixtureRoot = '';

    protected function setUp(): void
    {
        $this->fixtureRoot = sys_get_temp_dir() . '/gacela-preload-fixture-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $root = $this->fixtureRoot;

        // Removing a tree, so the root it walks is asserted to be the one this
        // test built and nothing above it, before anything is unlinked.
        self::assertNotSame('', $root);
        self::assertStringStartsWith(sys_get_temp_dir() . '/gacela-preload-fixture-', $root);
        self::assertStringStartsWith(sys_get_temp_dir(), $root);

        $this->fixtureRoot = '';

        if (!is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($root);
    }

    public function test_a_root_without_a_framework_directory_yields_nothing(): void
    {
        self::assertSame([], Preloader::classNames(__DIR__ . '/does-not-exist'));
    }

    public function test_the_framework_classes_are_discovered(): void
    {
        $classNames = Preloader::classNames($this->gacelaRoot());

        self::assertGreaterThan(100, count($classNames));
    }

    /**
     * The pillars, their resolvers and the container: the classes the feature
     * exists for. A hand-written list once named these while omitting the
     * parents, interfaces and traits they need, so none of them linked.
     */
    public function test_the_classes_the_framework_is_built_from_are_included(): void
    {
        $classNames = Preloader::classNames($this->gacelaRoot());

        foreach ([
            \Gacela\Framework\AbstractFacade::class,
            \Gacela\Framework\AbstractFactory::class,
            \Gacela\Framework\AbstractConfig::class,
            \Gacela\Framework\AbstractProvider::class,
            \Gacela\Framework\Gacela::class,
            \Gacela\Framework\ClassResolver\ClassInfo::class,
            \Gacela\Framework\Container\Container::class,
        ] as $expected) {
            self::assertContains($expected, $classNames);
        }
    }

    /**
     * The whole point of deriving the list: a class name that no longer has a
     * file behind it can never be produced.
     */
    public function test_every_discovered_class_has_a_file(): void
    {
        $root = $this->gacelaRoot();
        $prefixes = Preloader::autoloadPrefixes($root);

        foreach (Preloader::classNames($root) as $className) {
            // Resolved through the same rule the autoloader uses. Rebuilding the
            // path here would assume every class lives under src/Framework,
            // which stopped being true once the closure included its packages.
            self::assertNotNull(
                Preloader::fileFor($className, $prefixes),
                sprintf('%s resolves to no file', $className),
            );
        }
    }

    /**
     * The container is reached on the first `Container::withConfig()`. While it
     * was only *linkable* rather than preloaded, loading it cost more than
     * every other part of bootstrap put together.
     */
    public function test_the_packages_the_framework_runs_on_are_included(): void
    {
        $classNames = Preloader::classNames($this->gacelaRoot());

        self::assertContains(\Gacela\Container\Container::class, $classNames);
        self::assertContains(\Psr\Container\ContainerInterface::class, $classNames);
    }

    /**
     * Testing/ needs phpunit, which is a dev dependency. Loading it during a
     * production preload is a fatal in a context that cannot report one.
     */
    public function test_the_testing_helpers_are_excluded(): void
    {
        foreach (Preloader::classNames($this->gacelaRoot()) as $className) {
            self::assertStringNotContainsString('\Testing\\', $className);
        }
    }

    /**
     * A preload image built in filesystem order differs between machines for no
     * reason; sorting is what makes the logged count reproducible. Across the
     * whole closure rather than per package, so it does not depend on which
     * prefix happened to be walked first either.
     */
    public function test_the_order_is_sorted(): void
    {
        $classNames = Preloader::classNames($this->gacelaRoot());

        $sorted = $classNames;
        sort($sorted);

        self::assertSame($sorted, $classNames);
        self::assertNotSame([], $classNames);
    }

    /**
     * Linking needs the packages a framework class extends or implements, not
     * only the framework itself.
     */
    public function test_the_runtime_closure_is_covered_by_the_prefixes(): void
    {
        $prefixes = Preloader::autoloadPrefixes($this->gacelaRoot());

        self::assertArrayHasKey('Gacela\\Framework\\', $prefixes);
        self::assertArrayHasKey('Gacela\\Container\\', $prefixes);
        self::assertArrayHasKey('Psr\\Container\\', $prefixes);
    }

    public function test_every_prefix_points_at_a_directory_that_exists(): void
    {
        foreach (Preloader::autoloadPrefixes($this->gacelaRoot()) as $directory) {
            self::assertDirectoryExists($directory);
        }
    }

    /**
     * Spelled out rather than checked for existence: every one of these
     * directories has an existing ancestor, so "it is a directory" passes for a
     * path truncated to the wrong depth.
     */
    public function test_each_prefix_points_at_the_directory_that_holds_it(): void
    {
        $root = $this->gacelaRoot();

        self::assertSame([
            'Gacela\\Framework\\' => $root . '/src/Framework/',
            'Gacela\\Container\\' => $root . '/vendor/gacela-project/container/src/Container/',
            'Psr\\Container\\' => $root . '/vendor/psr/container/src/',
        ], Preloader::autoloadPrefixes($root));
    }

    /**
     * The layout every user actually runs: gacela installed under
     * `vendor/gacela-project/gacela`, with its siblings two levels up. Built as
     * a fixture because this repository is only ever the other layout.
     */
    public function test_the_vendor_directory_is_found_when_gacela_is_an_installed_package(): void
    {
        $vendor = $this->fixtureRoot . '/vendor';
        $installed = $vendor . '/gacela-project/gacela';

        mkdir($installed . '/src/Framework', 0o777, true);
        mkdir($vendor . '/psr/container/src', 0o777, true);
        mkdir($vendor . '/gacela-project/container/src/Container', 0o777, true);

        self::assertSame([
            'Gacela\\Framework\\' => $installed . '/src/Framework/',
            'Gacela\\Container\\' => $vendor . '/gacela-project/container/src/Container/',
            'Psr\\Container\\' => $vendor . '/psr/container/src/',
        ], Preloader::autoloadPrefixes($installed));
    }

    public function test_a_class_no_prefix_covers_resolves_to_no_file(): void
    {
        self::assertNull(Preloader::fileFor('Acme\Thing', Preloader::autoloadPrefixes($this->gacelaRoot())));
    }

    public function test_a_prefixed_class_resolves_to_the_file_below_its_directory(): void
    {
        $root = $this->gacelaRoot();

        self::assertSame(
            $root . '/src/Framework/ClassResolver/ClassInfo.php',
            Preloader::fileFor(ClassInfo::class, Preloader::autoloadPrefixes($root)),
        );
    }

    /**
     * The dependency that a framework class extends lives in another package,
     * which is the whole reason the prefixes are not just Gacela's own.
     */
    public function test_a_class_from_another_package_resolves_through_its_own_prefix(): void
    {
        $root = $this->gacelaRoot();

        self::assertSame(
            $root . '/vendor/psr/container/src/ContainerInterface.php',
            Preloader::fileFor(PsrContainerInterface::class, Preloader::autoloadPrefixes($root)),
        );
    }

    public function test_a_class_whose_prefix_matches_but_has_no_file_resolves_to_nothing(): void
    {
        self::assertNull(
            Preloader::fileFor('Gacela\Framework\NotAThing', Preloader::autoloadPrefixes($this->gacelaRoot())),
        );
    }

    /**
     * Without a vendor directory only the framework's own prefix can be known.
     */
    public function test_a_root_with_no_vendor_directory_maps_only_the_framework(): void
    {
        $prefixes = Preloader::autoloadPrefixes(__DIR__ . '/nowhere');

        self::assertSame(['Gacela\\Framework\\'], array_keys($prefixes));
    }

    public function test_running_it_links_every_discovered_class(): void
    {
        $root = $this->gacelaRoot();

        $result = Preloader::run($root);

        self::assertSame([], $result->skipped());
        self::assertSame(count(Preloader::classNames($root)), $result->linkedCount());
        self::assertStringEndsWith('0 skipped', $result->summary());
    }

    /**
     * A php file under `src/` that declares no class is reported rather than
     * counted: preloading it achieves nothing, and a silent success would say
     * the image holds something it does not.
     */
    public function test_a_file_declaring_no_class_is_reported_as_skipped(): void
    {
        $frameworkDir = $this->fixtureRoot . '/src/Framework';
        mkdir($frameworkDir, 0o777, true);
        file_put_contents($frameworkDir . '/NotAClass.php', "<?php\n// nothing declared here\n");

        $result = Preloader::run($this->fixtureRoot);

        self::assertSame(0, $result->linkedCount());
        self::assertSame(['Gacela\Framework\NotAClass'], $result->skipped());
        self::assertSame(
            'Gacela Opcache Preload: 0 classes linked, 1 skipped (Gacela\Framework\NotAClass)',
            $result->summary(),
        );
    }

    /**
     * A class whose parent is not installed is the realistic way this happens:
     * a partial or half-updated install, where the framework is there and one
     * of the packages it runs on is not.
     *
     * What matters is that the one class costs only itself. Preloading is
     * best-effort by design, so aborting the image over it would take a startup
     * that merely degrades and make it a boot failure -- and the reason has to
     * reach the operator, since it is the only account of what is missing.
     */
    public function test_a_class_whose_dependency_is_missing_is_reported_and_costs_only_itself(): void
    {
        $frameworkDir = $this->fixtureRoot . '/src/Framework';
        mkdir($frameworkDir, 0o777, true);
        file_put_contents(
            $frameworkDir . '/Fine.php',
            "<?php\nnamespace Gacela\\Framework;\nfinal class Fine {}\n",
        );
        file_put_contents(
            $frameworkDir . '/Broken.php',
            "<?php\nnamespace Gacela\\Framework;\nfinal class Broken extends \\Absent\\Package\\Missing {}\n",
        );

        $result = Preloader::run($this->fixtureRoot);

        self::assertSame(1, $result->linkedCount(), 'the healthy class still linked');
        self::assertCount(1, $result->skipped());
        self::assertStringContainsString('Gacela\Framework\Broken', $result->skipped()[0]);
        self::assertStringContainsString('Absent\Package\Missing', $result->skipped()[0]);
    }

    private function gacelaRoot(): string
    {
        return dirname(__DIR__, 4);
    }
}
