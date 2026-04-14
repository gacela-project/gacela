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
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleFacade;
use GacelaTest\Feature\Console\DebugModules\Fixtures\WidgetModule\WidgetModuleFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugModulesCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__ . '/Fixtures', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new DebugModulesCommand());
    }

    public function test_lists_every_discovered_module(): void
    {
        $exitCode = $this->command->execute([]);
        $output = $this->command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(WidgetModuleFacade::class, $output);
        self::assertStringContainsString(WidgetModuleFactory::class, $output);
        self::assertStringContainsString(GizmoModuleFacade::class, $output);
    }

    public function test_reports_pillars_without_constructor_as_resolvable(): void
    {
        $this->command->execute([]);
        $output = $this->command->getDisplay();

        self::assertStringContainsString('no constructor', $output);
        self::assertStringContainsString('Summary:', $output);
        self::assertStringContainsString('0 unresolvable', $output);
    }

    public function test_filter_argument_narrows_modules(): void
    {
        $this->command->execute(['filter' => 'WidgetModule']);
        $output = $this->command->getDisplay();

        self::assertStringContainsString(WidgetModuleFacade::class, $output);
        self::assertStringNotContainsString(GizmoModuleFacade::class, $output);
    }

    public function test_unknown_filter_reports_no_matches(): void
    {
        $exitCode = $this->command->execute(['filter' => 'DoesNotExist']);
        $output = $this->command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No modules match filter', $output);
    }

    public function test_filter_accepts_a_directory_path(): void
    {
        $this->command->execute(['filter' => __DIR__ . '/Fixtures/WidgetModule']);
        $output = $this->command->getDisplay();

        self::assertStringContainsString(WidgetModuleFacade::class, $output);
        self::assertStringNotContainsString(GizmoModuleFacade::class, $output);
    }

    public function test_surfaces_factory_whose_resolver_would_fail(): void
    {
        Gacela::bootstrap(__DIR__ . '/BrokenFixtures', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $command = new CommandTester(new DebugModulesCommand());
        $exitCode = $command->execute([]);
        $output = $command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(BrokenModuleFacade::class, $output);
        self::assertStringContainsString(BrokenModuleFactory::class, $output);
        self::assertStringContainsString('$dependency', $output);
        self::assertStringContainsString(UnboundDependency::class, $output);
        self::assertStringContainsString('interface, no binding', $output);
        self::assertStringContainsString('1 unresolvable', $output);
    }
}
