<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules;

use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugModules\BrokenFixtures\BrokenModule\BrokenModuleFactory;
use GacelaTest\Feature\Console\DebugModules\BrokenFixtures\BrokenModule\UnboundDependency;
use GacelaTest\Feature\Console\DebugModules\Fixtures\GizmoModule\GizmoModuleFacade;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleConfig;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleFacade;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleFactory;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleProvider;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModuleExtra\WidgetModuleExtraFacade;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule\AlphaCollaborator;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule\AlphaModuleFactory;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\BetaModule\BetaCollaborator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_column;
use function explode;
use function json_decode;
use function rtrim;
use function spl_autoload_register;
use function spl_autoload_unregister;

use function sprintf;

use const JSON_THROW_ON_ERROR;

final class DebugModulesCommandTest extends TestCase
{
    private const EXPLODING_FACADE = 'GacelaTest\\Feature\\Console\\DebugModules'
        . '\\ExplodingFixtures\\ExplodingModule\\ExplodingModuleFacade';

    private const AUTOLOAD_FAILURE = 'autoloading blew up';

    /** @var list<callable(string):void> */
    private array $registeredAutoloaders = [];

    protected function tearDown(): void
    {
        foreach ($this->registeredAutoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->registeredAutoloaders = [];
    }

    public function test_lists_every_discovered_module_and_its_pillars(): void
    {
        $tester = $this->debugModules('Fixtures', []);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Every discovered module contributes each pillar it defines.
        foreach ([
            GizmoModuleFacade::class,
            WidgetModuleExtraFacade::class,
            WidgetModuleFacade::class,
            WidgetModuleFactory::class,
            WidgetModuleConfig::class,
            WidgetModuleProvider::class,
        ] as $pillar) {
            self::assertStringContainsString($pillar, $display);
        }

        self::assertStringContainsString(
            'Summary: 3 modules, 6 pillars inspected, 0 unresolvable parameters',
            $display,
        );
    }

    public function test_a_namespace_filter_narrows_the_modules_and_the_header(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => 'GizmoModule']);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // The filter is echoed in the header, and narrows the listing to one module.
        self::assertStringContainsString('Gacela modules matching "GizmoModule"', $display);
        self::assertStringContainsString(GizmoModuleFacade::class, $display);
        self::assertStringNotContainsString(WidgetModuleFacade::class, $display);

        // Counts are singularised when only one of a thing was inspected.
        self::assertStringContainsString(
            'Summary: 1 module, 1 pillar inspected, 0 unresolvable parameters',
            $display,
        );
    }

    public function test_a_directory_filter_matches_only_that_directory(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => __DIR__ . '/Fixtures/WidgetModule']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(WidgetModuleFacade::class, $display);
        self::assertStringContainsString(
            'Summary: 1 module, 4 pillars inspected, 0 unresolvable parameters',
            $display,
        );

        // The sibling directory shares the "WidgetModule" prefix but is not inside
        // it, so an unanchored prefix match would wrongly pull it in.
        self::assertStringNotContainsString(WidgetModuleExtraFacade::class, $display);
    }

    public function test_a_directory_filter_keeps_every_module_below_it(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => __DIR__ . '/Fixtures']);
        $lines = $this->linesOf($tester);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('  ' . $this->moduleNameOf(GizmoModuleFacade::class), $lines);
        self::assertContains('  ' . $this->moduleNameOf(WidgetModuleExtraFacade::class), $lines);
        self::assertContains('  ' . $this->moduleNameOf(WidgetModuleFacade::class), $lines);
        self::assertContains('Summary: 3 modules, 6 pillars inspected, 0 unresolvable parameters', $lines);
    }

    public function test_unknown_filter_reports_no_matches(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => 'DoesNotExist']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'No modules match filter "DoesNotExist".',
            $tester->getDisplay(),
        );
    }

    public function test_reports_unresolvable_parameters_only_by_default(): void
    {
        $tester = $this->debugModules('MixedFixtures', []);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // A pillar with unresolvable parameters is flagged and its offenders itemised.
        self::assertStringContainsString(AlphaModuleFactory::class . ' (1 resolvable, 2 unresolvable)', $display);
        self::assertStringContainsString('$mandatory string (scalar without default)', $display);
        self::assertStringContainsString('$count int (scalar without default)', $display);

        // Without --detail the resolvable ones stay collapsed into the counts.
        self::assertStringNotContainsString('$collaborator', $display);

        self::assertStringContainsString(
            'Summary: 2 modules, 5 pillars inspected, 2 unresolvable parameters',
            $display,
        );
    }

    public function test_detail_adds_the_resolvable_parameters(): void
    {
        $tester = $this->debugModules('MixedFixtures', ['--detail' => true]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // --detail is what adds the resolvable parameters the default view omits.
        self::assertStringContainsString('$collaborator ' . AlphaCollaborator::class . ' (autowirable)', $display);
        self::assertStringContainsString('$collaborator ' . BetaCollaborator::class . ' (autowirable)', $display);

        // The unresolvable ones are still reported alongside them.
        self::assertStringContainsString('$mandatory string (scalar without default)', $display);

        self::assertStringContainsString(
            'Summary: 2 modules, 5 pillars inspected, 2 unresolvable parameters',
            $display,
        );
    }

    public function test_highlights_only_the_details_of_unresolvable_parameters(): void
    {
        $display = $this
            ->debugModules('MixedFixtures', ['--detail' => true], ['decorated' => true])
            ->getDisplay();

        self::assertStringContainsString("(\033[31mscalar without default\033[39m)", $display);
        self::assertStringContainsString('$collaborator ' . AlphaCollaborator::class . ' (autowirable)', $display);
    }

    public function test_surfaces_a_factory_whose_resolver_would_fail(): void
    {
        $tester = $this->debugModules('BrokenFixtures', []);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // A factory the container could not build is reported rather than thrown.
        self::assertStringContainsString(BrokenModuleFactory::class . ' (0 resolvable, 1 unresolvable)', $display);
        self::assertStringContainsString(
            '$dependency ' . UnboundDependency::class . ' (interface, no binding)',
            $display,
        );

        self::assertStringContainsString(
            'Summary: 1 module, 2 pillars inspected, 1 unresolvable parameter',
            $display,
        );
    }

    public function test_reports_a_discovery_failure_instead_of_crashing(): void
    {
        $autoloader = static function (string $class): void {
            if ($class === self::EXPLODING_FACADE) {
                throw new RuntimeException(self::AUTOLOAD_FAILURE);
            }
        };

        spl_autoload_register($autoloader, true, true);
        $this->registeredAutoloaders[] = $autoloader;

        $tester = $this->debugModules('ExplodingFixtures', []);

        // A module that cannot even be autoloaded is reported, not fatal.
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            'Could not discover modules: ' . self::AUTOLOAD_FAILURE,
            $tester->getDisplay(),
        );
    }

    /**
     * The command already counted the pillars nothing can build and exited
     * `SUCCESS` regardless, so acting on the count meant grepping the output --
     * which is what an exit code exists to avoid.
     */
    public function test_check_fails_when_a_pillar_needs_something_the_container_cannot_supply(): void
    {
        $tester = $this->debugModules('BrokenFixtures', ['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('the container cannot satisfy', $tester->getDisplay());
    }

    public function test_check_passes_when_every_pillar_resolves(): void
    {
        $tester = $this->debugModules('Fixtures', ['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Every inspected parameter can be satisfied', $tester->getDisplay());
    }

    /**
     * The reason `--check` is not simply `unresolvableCount() > 0`.
     *
     * A union-typed parameter is reported as unresolvable because the inspector
     * does not walk one -- that is a gap in this tool, and failing a build over
     * it would blame a project for using a language feature.
     */
    public function test_check_does_not_fail_on_a_parameter_it_declined_to_inspect(): void
    {
        $tester = $this->debugModules('UnionFixtures', ['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('not inspected', $tester->getDisplay());
    }

    /**
     * ...and it is still named, so a passing `--check` does not read as "every
     * parameter was checked".
     */
    public function test_an_uninspected_parameter_is_still_reported_as_unresolvable(): void
    {
        $tester = $this->debugModules('UnionFixtures', []);

        self::assertStringContainsString('1 unresolvable parameter', $tester->getDisplay());
    }

    /**
     * Without the flag the command reports and passes, exactly as before.
     */
    public function test_without_check_a_broken_module_still_exits_successfully(): void
    {
        $tester = $this->debugModules('BrokenFixtures', []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('the container cannot satisfy', $tester->getDisplay());
    }

    /**
     * `--check` answers "did anything fail" with an exit code; a job that wants
     * to say *which* pillar, and repeat the parameter into a review comment,
     * had to parse the prose. The same reason `doctor --json` exists.
     */
    public function test_json_names_the_pillar_and_the_parameter_that_cannot_be_satisfied(): void
    {
        $document = $this->debugModulesAsJson('BrokenFixtures', []);

        self::assertSame('error', $document['status']);
        self::assertSame(
            [['name' => 'dependency', 'type' => UnboundDependency::class, 'status' => 'unbound-interface',
                'detail' => 'interface, no binding', 'resolvable' => false]],
            $this->parametersOf($document, BrokenModuleFactory::class),
        );
    }

    /**
     * Without the sigil the text report prints: a document is matched against
     * a reflection parameter rather than read.
     */
    public function test_the_parameter_name_carries_no_dollar_sign(): void
    {
        $document = $this->debugModulesAsJson('BrokenFixtures', []);

        self::assertSame('dependency', $this->parametersOf($document, BrokenModuleFactory::class)[0]['name']);
    }

    public function test_the_summary_counts_match_the_text_report(): void
    {
        $document = $this->debugModulesAsJson('MixedFixtures', []);

        self::assertSame(
            ['modules' => 2, 'pillars' => 5, 'unresolvable' => 2, 'faults' => 2, 'notInspected' => 0],
            $document['summary'],
        );
    }

    /**
     * The distinction `--check` turns on, carried as its own field: a union
     * type is unresolvable and is not a fault, and a consumer deciding whether
     * to fail a build needs to tell those apart the way the exit code does.
     */
    public function test_a_parameter_the_inspector_declined_is_counted_apart_from_a_fault(): void
    {
        $document = $this->debugModulesAsJson('UnionFixtures', []);

        self::assertSame('ok', $document['status']);
        self::assertSame(1, $document['summary']['unresolvable']);
        self::assertSame(0, $document['summary']['faults']);
        self::assertSame(1, $document['summary']['notInspected']);
    }

    /**
     * `--detail` means in the document what it means in the text report, rather
     * than the document always carrying everything.
     */
    public function test_detail_adds_the_resolvable_parameters_to_the_document(): void
    {
        $without = $this->debugModulesAsJson('MixedFixtures', []);
        $with = $this->debugModulesAsJson('MixedFixtures', ['--detail' => true]);

        $names = fn (array $document): array => array_column(
            $this->parametersOf($document, AlphaModuleFactory::class),
            'name',
        );

        self::assertSame(['mandatory', 'count'], $names($without));
        self::assertSame(['collaborator', 'mandatory', 'count'], $names($with));
    }

    /**
     * `--json` on its own does not become a gate: the verdict is in the
     * document on every run, and only `--check` turns it into an exit code.
     */
    public function test_json_alone_does_not_fail_a_broken_module(): void
    {
        $tester = $this->debugModules('BrokenFixtures', ['--json' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('"status": "error"', $tester->getDisplay());
    }

    public function test_json_with_check_fails_and_still_emits_only_a_document(): void
    {
        $tester = $this->debugModules('BrokenFixtures', ['--json' => true, '--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        // The prose the text report ends with must not land inside the document.
        self::assertStringNotContainsString('the container cannot satisfy', $tester->getDisplay());
        self::assertIsArray(json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_a_filter_matching_nothing_is_an_empty_list_rather_than_a_sentence(): void
    {
        $document = $this->debugModulesAsJson('Fixtures', ['filter' => 'DoesNotExist']);

        self::assertSame([], $document['modules']);
        self::assertSame(0, $document['summary']['modules']);
    }

    /**
     * @param array<string, bool|string> $input
     *
     * @return array<string, mixed>
     */
    private function debugModulesAsJson(string $fixtureDirectory, array $input): array
    {
        $display = $this->debugModules($fixtureDirectory, $input + ['--json' => true])->getDisplay();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array<string, mixed>>
     */
    private function parametersOf(array $document, string $pillarClass): array
    {
        /** @var list<array{pillars: list<array{class: string, parameters: list<array<string, mixed>>}>}> $modules */
        $modules = $document['modules'];

        foreach ($modules as $module) {
            foreach ($module['pillars'] as $pillar) {
                if ($pillar['class'] === $pillarClass) {
                    return $pillar['parameters'];
                }
            }
        }

        self::fail(sprintf('no pillar %s in the document', $pillarClass));
    }

    /**
     * @param array<string, bool|string> $input
     * @param array<string, bool> $options
     */
    private function debugModules(string $fixtureDirectory, array $input, array $options = []): CommandTester
    {
        Gacela::bootstrap(__DIR__ . '/' . $fixtureDirectory, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $tester = new CommandTester(new DebugModulesCommand());
        $tester->execute($input, $options);

        return $tester;
    }

    private function moduleNameOf(string $facadeClass): string
    {
        return substr($facadeClass, 0, (int)strrpos($facadeClass, '\\'));
    }

    /**
     * @return list<string>
     */
    private function linesOf(CommandTester $tester): array
    {
        return array_map(
            rtrim(...),
            explode("\n", $tester->getDisplay()),
        );
    }
}
