<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\UnresolvedPillarFileCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * A module whose `BlogFactory.php` cannot be loaded is still a module: the
 * Facade resolves, discovery keeps it, and the Factory comes back `null`. So
 * `list:modules` prints a blank cell and `debug:module` says `(not found)`,
 * telling the reader they have no Factory while they look at the file they
 * wrote.
 */
final class UnresolvedPillarFileCheckTest extends TestCase
{
    private string $moduleDir = '';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        $this->moduleDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-pillar-' . bin2hex(random_bytes(4));
        mkdir($this->moduleDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Names exactly what this test created.
        foreach ($this->created as $file) {
            self::assertStringStartsWith($this->moduleDir . DIRECTORY_SEPARATOR, $file);
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->moduleDir)) {
            rmdir($this->moduleDir);
        }

        $this->created = [];
    }

    public function test_a_pillar_file_on_disk_that_resolved_to_nothing_is_reported(): void
    {
        $this->writeFile('BlogFactory.php');

        $result = $this->check($this->moduleWithNoPillars())->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['App\\Blog — BlogFactory.php is on disk and no Factory resolved'],
            $result->details,
        );
        // The whole sentence: a remediation is the one line a reader acts on,
        // and a substring assertion passes for half of it in the wrong order.
        self::assertSame(
            'the file is there and nothing can load it — check the `namespace` declaration '
            . 'matches the psr-4 prefix for its directory, then `composer dump-autoload`',
            $result->remediation,
        );
    }

    /**
     * The scan must not stop at the first pillar that *did* resolve. With the
     * Factory resolved and the Config's file unloadable, ending the walk early
     * reports nothing at all -- and a module usually has at least one working
     * pillar, so that is the ordinary case rather than the exotic one.
     */
    public function test_a_resolved_pillar_does_not_end_the_walk(): void
    {
        $this->writeFile('BlogConfig.php');

        $module = new AppModule('App\\Blog', 'Blog', self::class, self::class);

        self::assertSame(
            ['App\\Blog — BlogConfig.php is on disk and no Config resolved'],
            $this->check($module)->run()->details,
        );
    }

    /**
     * A project that redundantly declares a built-in suffix must not have the
     * same file reported twice.
     */
    public function test_a_suffix_configured_twice_reports_the_file_once(): void
    {
        $this->writeFile('BlogFactory.php');

        $result = $this->check($this->moduleWithNoPillars(), ['Factory' => ['Factory']])->run();

        self::assertCount(1, $result->details);
    }

    /**
     * The point of the check is the *file*. A module that simply has no Factory
     * is the ordinary case -- `--minimal` scaffolds exactly that -- and
     * reporting it would fire on every project.
     */
    public function test_a_module_with_no_such_file_is_not_reported(): void
    {
        $result = $this->check($this->moduleWithNoPillars())->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['every pillar file on disk resolved'], $result->details);
    }

    public function test_a_pillar_that_resolved_is_not_reported_even_with_its_file_there(): void
    {
        $this->writeFile('BlogFactory.php');

        $module = new AppModule('App\\Blog', 'Blog', self::class, self::class);

        self::assertSame(CheckStatus::Ok, $this->check($module)->run()->status);
    }

    /**
     * A `--short-name` module names the file after the kind alone.
     */
    public function test_a_short_name_pillar_file_is_recognised(): void
    {
        $this->writeFile('Config.php');

        $result = $this->check($this->moduleWithNoPillars())->run();

        self::assertSame(
            ['App\\Blog — Config.php is on disk and no Config resolved'],
            $result->details,
        );
    }

    /**
     * The suffix set is configurable, so a project on `addSuffixTypeProvider()`
     * gets the same answer for the name it actually uses.
     */
    public function test_a_configured_suffix_is_recognised(): void
    {
        $this->writeFile('BlogDependencyProvider.php');

        $result = $this->check(
            $this->moduleWithNoPillars(),
            ['Provider' => ['DependencyProvider']],
        )->run();

        self::assertSame(
            ['App\\Blog — BlogDependencyProvider.php is on disk and no Provider resolved'],
            $result->details,
        );
    }

    /**
     * Every unresolved pillar is named, not just the first: a module with two
     * broken files gets two lines, or the second is a second round trip.
     */
    public function test_every_unresolved_pillar_file_is_named(): void
    {
        $this->writeFile('BlogFactory.php');
        $this->writeFile('BlogConfig.php');

        self::assertCount(2, $this->check($this->moduleWithNoPillars())->run()->details);
    }

    /**
     * @param array<string, list<string>> $suffixTypes
     */
    private function check(AppModule $module, array $suffixTypes = []): UnresolvedPillarFileCheck
    {
        $directory = $this->moduleDir;

        return new UnresolvedPillarFileCheck(
            [$module],
            $suffixTypes,
            static fn (string $className): string => $directory . DIRECTORY_SEPARATOR . 'BlogFacade.php',
        );
    }

    private function moduleWithNoPillars(): AppModule
    {
        return new AppModule('App\\Blog', 'Blog', self::class);
    }

    private function writeFile(string $filename): void
    {
        $path = $this->moduleDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, "<?php\n");
        $this->created[] = $path;
    }
}
