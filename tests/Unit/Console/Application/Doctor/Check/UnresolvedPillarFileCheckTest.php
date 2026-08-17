<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\UnresolvedPillarFileCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\AllAppModules\PillarResolutionFailure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_starts_with;
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
     * The reason the remediation exists at all.
     *
     * `AppModuleCreator` catches whatever resolution threw, so a
     * `DependencyNotFoundException` from the Factory's own constructor arrives
     * here as the same `null` a missing class does. This check then asserted a
     * cause nobody had looked at: the namespace was right, the psr-4 prefix was
     * right, the class loaded -- and the one line printed to get somebody
     * unstuck sent them to the only place already known to be fine (#884).
     */
    public function test_a_pillar_that_failed_to_build_names_the_failure_rather_than_blaming_the_namespace(): void
    {
        $this->writeFile('ConsoleConfig.php');

        $result = $this->check($this->consoleModuleFailing(
            new RuntimeException('no concrete class was found for ClockInterface'),
        ))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['Gacela\\Console — ConsoleConfig.php is on disk and no Config resolved: '
                . 'RuntimeException: no concrete class was found for ClockInterface'],
            $result->details,
        );
        self::assertSame(
            'the class loads, so the `namespace` and the psr-4 prefix are not the problem '
            . '— fix the failure named on each line',
            $result->remediation,
        );
    }

    /**
     * The two signals are not the same question. A file PHP cannot compile
     * throws *and* leaves no class behind, and there the namespace tip is the
     * useful one -- so the thrown reason goes on the line either way, and only
     * the remediation turns on whether the class is there.
     */
    public function test_a_pillar_whose_class_does_not_exist_keeps_the_namespace_tip_and_still_names_what_threw(): void
    {
        $this->writeFile('BlogFactory.php');

        $result = $this->check($this->moduleWithNoPillars([
            'Factory' => PillarResolutionFailure::from(new RuntimeException('syntax error, unexpected token')),
        ]))->run();

        self::assertSame(
            ['App\\Blog — BlogFactory.php is on disk and no Factory resolved: '
                . 'RuntimeException: syntax error, unexpected token'],
            $result->details,
        );
        self::assertSame($this->namespaceTip(), $result->remediation);
    }

    /**
     * The other half of the pair: the class is there and nothing threw, so
     * there is no reason to print and none to point at. The namespace tip is
     * what is left, and it stays.
     */
    public function test_a_pillar_whose_class_loads_but_recorded_no_failure_keeps_the_namespace_tip(): void
    {
        $this->writeFile('ConsoleConfig.php');

        $result = $this->check($this->consoleModule())->run();

        self::assertSame(
            ['Gacela\\Console — ConsoleConfig.php is on disk and no Config resolved'],
            $result->details,
        );
        self::assertSame($this->namespaceTip(), $result->remediation);
    }

    /**
     * One remediation is printed for the whole finding, so a run holding both
     * kinds gets the tip that is still worth following -- each line carries its
     * own reason regardless.
     */
    public function test_one_unexplained_file_among_explained_ones_keeps_the_namespace_tip(): void
    {
        $this->writeFile('ConsoleConfig.php');
        $this->writeFile('BlogFactory.php');

        $result = $this->checkAll([
            $this->consoleModuleFailing(new RuntimeException('cannot build')),
            $this->moduleWithNoPillars(),
        ])->run();

        self::assertCount(2, $result->details);
        self::assertSame($this->namespaceTip(), $result->remediation);
    }

    /**
     * `DependencyNotFoundException` spans five lines and ends in a URL. A
     * detail is one line of the report, and a message pasted in raw takes the
     * indentation of the lines under it with it, so the words survive and the
     * newlines do not.
     *
     * Leading and trailing whitespace included: a message built from a heredoc
     * ends in a newline, and folding without trimming turns that into a space
     * hanging off the end of the line.
     */
    public function test_a_multi_line_failure_message_is_folded_onto_the_detail_line(): void
    {
        $this->writeFile('ConsoleConfig.php');

        $result = $this->check($this->consoleModuleFailing(
            new RuntimeException("\nNo concrete class was found that implements:\n\"Clock\"\n\nDid you forget?\n"),
        ))->run();

        self::assertSame(
            ['Gacela\\Console — ConsoleConfig.php is on disk and no Config resolved: '
                . 'RuntimeException: No concrete class was found that implements: "Clock" Did you forget?'],
            $result->details,
        );
    }

    /**
     * Asking whether the class is there is asking PHP to load it, and a file
     * that could not be compiled the first time throws again on the second. The
     * check answers the reader either way rather than dying while telling them
     * what went wrong.
     */
    public function test_a_class_whose_autoloading_throws_does_not_take_the_check_down_with_it(): void
    {
        $this->writeFile('BlogFactory.php');

        $autoloader = static function (string $className): void {
            if (str_starts_with($className, 'App\\Blog')) {
                throw new RuntimeException('parse error while autoloading ' . $className);
            }
        };
        spl_autoload_register($autoloader);

        try {
            $result = $this->check($this->moduleWithNoPillars([
                'Factory' => PillarResolutionFailure::from(new RuntimeException('unexpected end of file')),
            ]))->run();

            self::assertSame(
                ['App\\Blog — BlogFactory.php is on disk and no Factory resolved: '
                    . 'RuntimeException: unexpected end of file'],
                $result->details,
            );
            self::assertSame($this->namespaceTip(), $result->remediation);
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }

    private function namespaceTip(): string
    {
        return 'the file is there and nothing can load it — check the `namespace` declaration '
            . 'matches the psr-4 prefix for its directory, then `composer dump-autoload`';
    }

    /**
     * @param array<string, list<string>> $suffixTypes
     */
    private function check(AppModule $module, array $suffixTypes = []): UnresolvedPillarFileCheck
    {
        return $this->checkAll([$module], $suffixTypes);
    }

    /**
     * @param list<AppModule> $modules
     * @param array<string, list<string>> $suffixTypes
     */
    private function checkAll(array $modules, array $suffixTypes = []): UnresolvedPillarFileCheck
    {
        $directory = $this->moduleDir;

        return new UnresolvedPillarFileCheck(
            $modules,
            $suffixTypes,
            static fn (string $className): string => $directory . DIRECTORY_SEPARATOR . 'BlogFacade.php',
        );
    }

    /**
     * A module of this framework's own, so `Gacela\Console\ConsoleConfig` is a
     * class that genuinely loads -- which is the fact the remediation turns on,
     * and one a name invented for a test cannot supply.
     */
    private function consoleModule(): AppModule
    {
        return new AppModule('Gacela\\Console', 'Console', ConsoleFacade::class);
    }

    private function consoleModuleFailing(Throwable $throwable): AppModule
    {
        return new AppModule('Gacela\\Console', 'Console', ConsoleFacade::class, resolutionFailures: [
            'Config' => PillarResolutionFailure::from($throwable),
        ]);
    }

    /**
     * @param array<string, PillarResolutionFailure> $resolutionFailures
     */
    private function moduleWithNoPillars(array $resolutionFailures = []): AppModule
    {
        return new AppModule('App\\Blog', 'Blog', self::class, resolutionFailures: $resolutionFailures);
    }

    private function writeFile(string $filename): void
    {
        $path = $this->moduleDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, "<?php\n");
        $this->created[] = $path;
    }
}
