<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\Testing;

use Closure;
use Gacela\Console\Testing\ModuleAssertionException;
use Gacela\Console\Testing\ModuleAssertions;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Alpha\AlphaFacade;
use GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Beta\BetaFacade;
use GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Gamma\GammaFacade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A boundary that holds in CI and not in the test suite is a boundary nobody
 * trusts, so these assertions read the graph `debug:graph --check` reads, with
 * the parser, the cycle detector and the rule checker that command uses.
 *
 * The fixture application has its problems on purpose: Alpha and Beta import
 * each other, Gamma is a leaf. Every failure below is a real finding about a
 * real graph rather than a hand-written array.
 */
final class ModuleAssertionsTest extends TestCase
{
    use ModuleAssertions;

    private const FIXTURE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'ModuleBoundaryFixture';

    private const ALPHA = 'GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Alpha';

    private const BETA = 'GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Beta';

    private const GAMMA = 'GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture\Gamma';

    protected function setUp(): void
    {
        Gacela::bootstrap(self::FIXTURE_DIR, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_a_leaf_module_depends_on_nothing(): void
    {
        self::assertModuleDependsOnlyOn(GammaFacade::class, []);
    }

    public function test_a_module_within_its_allowed_list_holds(): void
    {
        self::assertModuleDependsOnlyOn(AlphaFacade::class, [BetaFacade::class, GammaFacade::class]);
    }

    /**
     * The acceptance criterion: the same edge `debug:graph --check` fails on,
     * named together with the file and line that write it.
     */
    public function test_a_dependency_outside_the_allowed_list_names_the_edge_and_where_it_is_written(): void
    {
        $failure = $this->failureOf(
            static fn () => self::assertModuleDependsOnlyOn(AlphaFacade::class, [GammaFacade::class]),
        );

        self::assertStringContainsString(self::ALPHA . ' -> ' . self::BETA, $failure);
        // The separator the path is written with is the platform's; the file
        // and the line are what the message has to carry.
        self::assertStringContainsString('ModuleBoundaryFixture', $failure);
        self::assertStringContainsString('AlphaFacade.php:8', $failure);
        self::assertStringContainsString(BetaFacade::class, $failure);
    }

    /**
     * A dependency the list does allow must not be reported alongside one it
     * does not, or the reader is sent to two files to fix one problem.
     */
    public function test_only_the_dependencies_outside_the_list_are_reported(): void
    {
        $failure = $this->failureOf(
            static fn () => self::assertModuleDependsOnlyOn(AlphaFacade::class, [BetaFacade::class]),
        );

        self::assertStringContainsString(self::ALPHA . ' -> ' . self::GAMMA, $failure);
        self::assertStringNotContainsString(self::ALPHA . ' -> ' . self::BETA, $failure);
    }

    /**
     * A module and its allowances can be named by namespace, which is how a
     * decision about a whole area of the application is written -- and the only
     * form available for a namespace that holds several modules.
     */
    public function test_a_module_and_its_allowances_can_be_named_by_namespace(): void
    {
        self::assertModuleDependsOnlyOn(self::ALPHA, [
            'GacelaTest\Integration\Console\Testing\ModuleBoundaryFixture',
        ]);
    }

    /**
     * The mistake that would otherwise pass silently: an assertion about a
     * module the scan never saw has nothing to check, and its empty dependency
     * list reads exactly like a boundary holding.
     */
    public function test_a_module_the_scan_did_not_find_fails_naming_the_modules_it_did(): void
    {
        $failure = $this->failureOf(
            static fn () => self::assertModuleDependsOnlyOn('App\Nope', []),
        );

        self::assertStringContainsString('App\Nope', $failure);
        self::assertStringContainsString(self::ALPHA, $failure);
        self::assertStringContainsString(self::GAMMA, $failure);
    }

    public function test_an_undeclared_cycle_is_reported_with_the_imports_that_create_it(): void
    {
        $failure = $this->failureOf(static fn () => self::assertNoModuleCycles());

        self::assertStringContainsString(self::ALPHA . ' -> ' . self::BETA, $failure);
        self::assertStringContainsString('BetaFacade.php:8', $failure);
    }

    public function test_a_reviewed_cycle_in_the_allow_list_holds(): void
    {
        self::assertNoModuleCycles($this->fixtureFile('allowed-cycles.json'));
    }

    /**
     * Self-invalidating, exactly as the CLI is: an allowance that outlives its
     * cycle is the check having quietly stopped watching.
     */
    public function test_an_allowance_for_a_cycle_that_no_longer_exists_fails(): void
    {
        $failure = $this->failureOf(
            fn () => self::assertNoModuleCycles($this->fixtureFile('allowed-cycles-stale.json')),
        );

        self::assertStringContainsString('no longer exists', $failure);
        self::assertStringContainsString(self::GAMMA, $failure);
    }

    public function test_declared_rules_the_graph_respects_hold(): void
    {
        self::assertModuleRulesHold($this->fixtureFile('module-rules.json'));
    }

    public function test_a_forbidden_dependency_names_the_rules_reason_and_the_import(): void
    {
        $failure = $this->failureOf(
            fn () => self::assertModuleRulesHold($this->fixtureFile('module-rules-violated.json')),
        );

        self::assertStringContainsString(self::ALPHA . ' -> ' . self::BETA, $failure);
        self::assertStringContainsString('alpha owns the decision', $failure);
        self::assertStringContainsString('AlphaFacade.php:8', $failure);
    }

    public function test_a_rule_that_governs_no_module_fails(): void
    {
        $failure = $this->failureOf(
            fn () => self::assertModuleRulesHold($this->fixtureFile('module-rules-governing-nothing.json')),
        );

        self::assertStringContainsString('governs nothing', $failure);
        self::assertStringContainsString('Delta', $failure);
    }

    /**
     * A file that cannot be read is the test's own setup being wrong, and a
     * boundary failure would send the reader looking at the application.
     */
    public function test_an_unreadable_allowed_cycles_file_is_a_setup_error_not_a_finding(): void
    {
        $missing = $this->fixtureFile('there-is-no-such-file.json');

        $this->expectException(ModuleAssertionException::class);
        $this->expectExceptionMessage($missing);

        self::assertNoModuleCycles($missing);
    }

    private function fixtureFile(string $name): string
    {
        return self::FIXTURE_DIR . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * The failure message of an assertion that was expected to fail.
     *
     * `RuntimeException` rather than PHPUnit's own `AssertionFailedError`: that
     * class is `@internal`, and a test suite that catches it is coupled to a
     * hierarchy PHPUnit does not promise. Every failure these assertions raise
     * descends from `RuntimeException`, so the wider catch loses nothing -- and
     * a {@see ModuleAssertionException} arriving here instead would fail on the
     * message it carries, which names the file it could not read.
     *
     * @param Closure():void $assertion
     */
    private function failureOf(Closure $assertion): string
    {
        try {
            $assertion();
        } catch (RuntimeException $runtimeException) {
            return $runtimeException->getMessage();
        }

        self::fail('The assertion was expected to fail, and passed.');
    }
}
