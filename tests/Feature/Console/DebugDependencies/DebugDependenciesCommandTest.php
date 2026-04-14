<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies;

use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundContract;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundImplementation;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\MixedDependenciesService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\NoConstructorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugDependenciesCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });

        $this->command = new CommandTester(new DebugDependenciesCommand());
    }

    public function test_unknown_class_fails(): void
    {
        $exitCode = $this->command->execute(['class' => 'Does\\Not\\Exist']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('does not exist', $this->command->getDisplay());
    }

    public function test_interface_fails(): void
    {
        $exitCode = $this->command->execute(['class' => BoundContract::class]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('is an interface', $this->command->getDisplay());
    }

    public function test_class_without_constructor_reports_no_constructor(): void
    {
        $exitCode = $this->command->execute(['class' => NoConstructorService::class]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No constructor', $this->command->getDisplay());
    }

    public function test_mixed_dependencies_are_categorized(): void
    {
        $exitCode = $this->command->execute(['class' => MixedDependenciesService::class]);
        $output = $this->command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(MixedDependenciesService::class, $output);

        self::assertStringContainsString('$bound', $output);
        self::assertStringContainsString('bound -> ' . BoundImplementation::class, $output);

        self::assertStringContainsString('$collaborator', $output);
        self::assertStringContainsString('(autowirable)', $output);

        self::assertStringContainsString('$unbound', $output);
        self::assertStringContainsString('interface, no binding', $output);

        self::assertStringContainsString('$mandatoryScalar', $output);
        self::assertStringContainsString('scalar without default', $output);

        self::assertStringContainsString('$optionalScalar', $output);
        self::assertStringContainsString("= 'default'", $output);

        self::assertStringContainsString('$nullableCollaborator', $output);

        self::assertStringContainsString('Resolvable:', $output);
        self::assertStringContainsString('Unresolvable:', $output);
    }
}
