<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugContainer;

use Closure;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\AutowirableCollaborator;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundContract;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundImplementation;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\MixedDependenciesService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\UnboundContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

final class DebugContainerCommandTest extends TestCase
{
    /** Every counter the statistics view reports. */
    private const COUNTERS = [
        'Registered Services',
        'Frozen Services',
        'Factory Services',
        'User Bindings',
        'Cached Dependencies',
    ];

    public function test_no_arguments_shows_the_statistics_of_an_empty_container(): void
    {
        $tester = $this->debugContainer([]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Container Statistics', $display);

        // Nothing has been registered, so every counter reads zero.
        foreach (self::COUNTERS as $counter) {
            self::assertMatchesRegularExpression('/' . preg_quote($counter, '/') . ': 0\b/', $display);
        }

        self::assertStringContainsString('Container is empty', $display);
    }

    public function test_the_memory_usage_of_the_container_is_reported(): void
    {
        $tester = $this->debugContainer([]);

        self::assertMatchesRegularExpression(
            '/Memory Usage: \d+(\.\d+)? [KMG]?B/',
            $tester->getDisplay(),
        );
    }

    public function test_a_populated_container_reports_its_services_and_bindings(): void
    {
        $tester = $this->debugContainer([], static function (GacelaConfig $config): void {
            $config->addBinding(BoundContract::class, BoundImplementation::class);
            $config->addFactory('some-factory', static fn (): string => 'value');
            $config->addProtected('some-protected', static fn (): string => 'value');
        });

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // The counters reflect what was registered: a factory and a protected
        // service, plus one interface-to-implementation binding.
        self::assertMatchesRegularExpression('/Registered Services: 2\b/', $display);
        self::assertMatchesRegularExpression('/Factory Services: 1\b/', $display);
        self::assertMatchesRegularExpression('/User Bindings: 1\b/', $display);

        // The "container is empty" hint belongs to the empty container only.
        self::assertStringNotContainsString('Container is empty', $display);
    }

    public function test_stats_flag_takes_precedence_over_the_class_argument(): void
    {
        $tester = $this->debugContainer(['class' => ConsoleFacade::class, '--stats' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Container Statistics', $tester->getDisplay());
        self::assertStringNotContainsString('Dependency Tree', $tester->getDisplay());
    }

    public function test_class_argument_without_flag_shows_the_dependency_tree(): void
    {
        $tester = $this->debugContainer(
            ['class' => MixedDependenciesService::class],
            static function (GacelaConfig $config): void {
                $config->addBinding(BoundContract::class, BoundImplementation::class);
            },
        );

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dependency Tree for ' . MixedDependenciesService::class, $display);

        // Drawn where each sits, labelled with the parameter that pulled it in,
        // and marked by whether the container can supply it.
        self::assertStringContainsString('├── ✓ $bound: ' . BoundImplementation::class, $display);
        self::assertStringContainsString('├── ✓ $collaborator: ' . AutowirableCollaborator::class, $display);
        self::assertStringContainsString('├── ✗ $unbound: ' . UnboundContract::class, $display);

        // The count is of distinct classes: AutowirableCollaborator satisfies
        // two parameters, so it is drawn twice and counted once.
        self::assertStringContainsString('└── ✓ $nullableCollaborator: ' . AutowirableCollaborator::class, $display);
        self::assertStringContainsString('Total Dependencies: 3', $display);
    }

    public function test_the_tree_flag_shows_the_dependency_tree_too(): void
    {
        $tester = $this->debugContainer(['class' => ConsoleFacade::class, '--tree' => true]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Dependency Tree for ' . ConsoleFacade::class, $display);
        self::assertStringContainsString(sprintf('Class "%s" has no dependencies', ConsoleFacade::class), $display);
    }

    public function test_the_tree_flag_requires_a_class_name(): void
    {
        $tester = $this->debugContainer(['--tree' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('The --tree option requires a class name argument', $tester->getDisplay());
    }

    public function test_an_unknown_class_fails(): void
    {
        $tester = $this->debugContainer(['class' => 'Does\\Not\\Exist']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Class "Does\\Not\\Exist" does not exist', $tester->getDisplay());
    }

    /**
     * @param array<string, bool|string> $input
     * @param null|Closure(GacelaConfig):void $configFn
     */
    private function debugContainer(array $input, ?Closure $configFn = null): CommandTester
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            if ($configFn !== null) {
                $configFn($config);
            }
        });

        $tester = new CommandTester(new DebugContainerCommand());
        $tester->execute($input);

        return $tester;
    }
}
