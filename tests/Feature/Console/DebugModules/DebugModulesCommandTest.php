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

use function explode;
use function rtrim;
use function spl_autoload_register;
use function spl_autoload_unregister;

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
