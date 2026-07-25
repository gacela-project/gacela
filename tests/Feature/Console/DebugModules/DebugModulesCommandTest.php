<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules;

use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugModules\BrokenFixtures\BrokenModule\BrokenModuleFacade;
use GacelaTest\Feature\Console\DebugModules\BrokenFixtures\BrokenModule\BrokenModuleFactory;
use GacelaTest\Feature\Console\DebugModules\BrokenFixtures\BrokenModule\UnboundDependency;
use GacelaTest\Feature\Console\DebugModules\Fixtures\GizmoModule\GizmoModuleFacade;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleConfig;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleFacade;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleFactory;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleProvider;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModuleExtra\WidgetModuleExtraFacade;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule\AlphaCollaborator;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule\AlphaModuleConfig;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule\AlphaModuleFacade;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule\AlphaModuleFactory;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\BetaModule\BetaCollaborator;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\BetaModule\BetaModuleConfig;
use GacelaTest\Feature\Console\DebugModules\MixedFixtures\BetaModule\BetaModuleFacade;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_slice;
use function explode;
use function rtrim;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_repeat;

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

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Gacela modules: constructor resolvability',
            self::separator(),
            '',
            '  ' . self::moduleNameOf(GizmoModuleFacade::class),
            '    ✓ ' . GizmoModuleFacade::class . ' (no constructor)',
            '',
            '  ' . self::moduleNameOf(WidgetModuleExtraFacade::class),
            '    ✓ ' . WidgetModuleExtraFacade::class . ' (no constructor)',
            '',
            '  ' . self::moduleNameOf(WidgetModuleFacade::class),
            '    ✓ ' . WidgetModuleFacade::class . ' (no constructor)',
            '    ✓ ' . WidgetModuleFactory::class . ' (no constructor)',
            '    ✓ ' . WidgetModuleConfig::class . ' (no constructor)',
            '    ✓ ' . WidgetModuleProvider::class . ' (no constructor)',
            '',
            'Summary: 3 modules, 6 pillars inspected, 0 unresolvable parameters',
            '',
            '',
        ], self::linesOf($tester));
    }

    public function test_a_namespace_filter_narrows_the_modules_and_the_header(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => 'GizmoModule']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Gacela modules matching "GizmoModule"',
            self::separator(),
            '',
            '  ' . self::moduleNameOf(GizmoModuleFacade::class),
            '    ✓ ' . GizmoModuleFacade::class . ' (no constructor)',
            '',
            'Summary: 1 module, 1 pillar inspected, 0 unresolvable parameters',
            '',
            '',
        ], self::linesOf($tester));
    }

    public function test_a_directory_filter_matches_only_that_directory(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => __DIR__ . '/Fixtures/WidgetModule']);
        $lines = self::linesOf($tester);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '  ' . self::moduleNameOf(WidgetModuleFacade::class),
            '    ✓ ' . WidgetModuleFacade::class . ' (no constructor)',
            '    ✓ ' . WidgetModuleFactory::class . ' (no constructor)',
            '    ✓ ' . WidgetModuleConfig::class . ' (no constructor)',
            '    ✓ ' . WidgetModuleProvider::class . ' (no constructor)',
            '',
            'Summary: 1 module, 4 pillars inspected, 0 unresolvable parameters',
            '',
            '',
        ], array_slice($lines, 4));

        // The sibling directory shares the "WidgetModule" prefix but is not inside it.
        self::assertNotContains('  ' . self::moduleNameOf(WidgetModuleExtraFacade::class), $lines);
    }

    public function test_a_directory_filter_keeps_every_module_below_it(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => __DIR__ . '/Fixtures']);
        $lines = self::linesOf($tester);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('  ' . self::moduleNameOf(GizmoModuleFacade::class), $lines);
        self::assertContains('  ' . self::moduleNameOf(WidgetModuleExtraFacade::class), $lines);
        self::assertContains('  ' . self::moduleNameOf(WidgetModuleFacade::class), $lines);
        self::assertContains('Summary: 3 modules, 6 pillars inspected, 0 unresolvable parameters', $lines);
    }

    public function test_unknown_filter_reports_no_matches(): void
    {
        $tester = $this->debugModules('Fixtures', ['filter' => 'DoesNotExist']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Gacela modules matching "DoesNotExist"',
            self::separator(),
            '',
            '  No modules match filter "DoesNotExist".',
            '',
            '',
        ], self::linesOf($tester));
    }

    public function test_reports_unresolvable_parameters_only_by_default(): void
    {
        $tester = $this->debugModules('MixedFixtures', []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Gacela modules: constructor resolvability',
            self::separator(),
            '',
            '  ' . self::moduleNameOf(AlphaModuleFacade::class),
            '    ✓ ' . AlphaModuleFacade::class . ' (no constructor)',
            '    ✗ ' . AlphaModuleFactory::class . ' (1 resolvable, 2 unresolvable)',
            '       ✗ $mandatory string (scalar without default)',
            '       ✗ $count int (scalar without default)',
            '    ✓ ' . AlphaModuleConfig::class . ' (constructor takes no parameters)',
            '',
            '  ' . self::moduleNameOf(BetaModuleFacade::class),
            '    ✓ ' . BetaModuleFacade::class . ' (no constructor)',
            '    ✓ ' . BetaModuleConfig::class . ' (1 resolvable, 0 unresolvable)',
            '',
            'Summary: 2 modules, 5 pillars inspected, 2 unresolvable parameters',
            'Run bin/gacela debug:dependencies <class> for a per-class view.',
            '',
            '',
        ], self::linesOf($tester));
    }

    public function test_detail_adds_the_resolvable_parameters(): void
    {
        $tester = $this->debugModules('MixedFixtures', ['--detail' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Gacela modules: constructor resolvability',
            self::separator(),
            '',
            '  ' . self::moduleNameOf(AlphaModuleFacade::class),
            '    ✓ ' . AlphaModuleFacade::class . ' (no constructor)',
            '    ✗ ' . AlphaModuleFactory::class . ' (1 resolvable, 2 unresolvable)',
            '       ✓ $collaborator ' . AlphaCollaborator::class . ' (autowirable)',
            '       ✗ $mandatory string (scalar without default)',
            '       ✗ $count int (scalar without default)',
            '    ✓ ' . AlphaModuleConfig::class . ' (constructor takes no parameters)',
            '',
            '  ' . self::moduleNameOf(BetaModuleFacade::class),
            '    ✓ ' . BetaModuleFacade::class . ' (no constructor)',
            '    ✓ ' . BetaModuleConfig::class . ' (1 resolvable, 0 unresolvable)',
            '       ✓ $collaborator ' . BetaCollaborator::class . ' (autowirable)',
            '',
            'Summary: 2 modules, 5 pillars inspected, 2 unresolvable parameters',
            'Run bin/gacela debug:dependencies <class> for a per-class view.',
            '',
            '',
        ], self::linesOf($tester));
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

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Gacela modules: constructor resolvability',
            self::separator(),
            '',
            '  ' . self::moduleNameOf(BrokenModuleFacade::class),
            '    ✓ ' . BrokenModuleFacade::class . ' (no constructor)',
            '    ✗ ' . BrokenModuleFactory::class . ' (0 resolvable, 1 unresolvable)',
            '       ✗ $dependency ' . UnboundDependency::class . ' (interface, no binding)',
            '',
            'Summary: 1 module, 2 pillars inspected, 1 unresolvable parameter',
            'Run bin/gacela debug:dependencies <class> for a per-class view.',
            '',
            '',
        ], self::linesOf($tester));
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

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'Could not discover modules: ' . self::AUTOLOAD_FAILURE,
            '',
        ], self::linesOf($tester));
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

    private static function moduleNameOf(string $facadeClass): string
    {
        return substr($facadeClass, 0, (int)strrpos($facadeClass, '\\'));
    }

    private static function separator(): string
    {
        return str_repeat('=', 60);
    }

    /**
     * @return list<string>
     */
    private static function linesOf(CommandTester $tester): array
    {
        return array_map(
            static fn (string $line): string => rtrim($line),
            explode("\n", $tester->getDisplay()),
        );
    }
}
