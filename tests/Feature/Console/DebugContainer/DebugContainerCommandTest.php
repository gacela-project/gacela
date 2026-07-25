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

use function array_slice;
use function explode;
use function rtrim;
use function sprintf;
use function str_repeat;

final class DebugContainerCommandTest extends TestCase
{
    public function test_no_arguments_shows_the_statistics_of_an_empty_container(): void
    {
        $tester = $this->debugContainer([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Container Statistics',
            self::separator(),
            '',
            'Registered Services: 0',
            'Frozen Services: 0',
            'Factory Services: 0',
            'User Bindings: 0',
            'Cached Dependencies: 0',
        ], array_slice(self::linesOf($tester), 0, 9));

        self::assertSame([
            '',
            'Container is empty - no services registered yet',
            '',
            'Note: This shows only user-defined bindings and plugins.',
            "Gacela's internal services are not included in these statistics.",
            '',
            '',
        ], array_slice(self::linesOf($tester), 10));
    }

    public function test_the_memory_usage_of_the_container_is_reported(): void
    {
        $tester = $this->debugContainer([]);

        self::assertMatchesRegularExpression(
            '/^Memory Usage: \d+(\.\d+)? [KMG]?B$/',
            self::linesOf($tester)[9],
        );
    }

    public function test_a_populated_container_reports_its_services_and_bindings(): void
    {
        $tester = $this->debugContainer([], static function (GacelaConfig $config): void {
            $config->addBinding(BoundContract::class, BoundImplementation::class);
            $config->addFactory('some-factory', static fn (): string => 'value');
            $config->addProtected('some-protected', static fn (): string => 'value');
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Registered Services: 2',
            'Frozen Services: 0',
            'Factory Services: 1',
            'User Bindings: 1',
            'Cached Dependencies: 0',
        ], array_slice(self::linesOf($tester), 4, 5));

        // The "container is empty" hint belongs to the empty container only.
        self::assertSame([
            '',
            'Note: This shows only user-defined bindings and plugins.',
            "Gacela's internal services are not included in these statistics.",
            '',
            '',
        ], array_slice(self::linesOf($tester), 10));
    }

    public function test_stats_flag_takes_precedence_over_the_class_argument(): void
    {
        $tester = $this->debugContainer(['class' => ConsoleFacade::class, '--stats' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('Container Statistics', self::linesOf($tester)[1]);
    }

    public function test_class_argument_without_flag_shows_the_dependency_tree(): void
    {
        $tester = $this->debugContainer(
            ['class' => MixedDependenciesService::class],
            static function (GacelaConfig $config): void {
                $config->addBinding(BoundContract::class, BoundImplementation::class);
            },
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Dependency Tree for ' . MixedDependenciesService::class,
            self::separator(),
            '',
            '1. ' . BoundImplementation::class,
            '2. ' . AutowirableCollaborator::class,
            '3. ' . UnboundContract::class,
            '',
            'Total Dependencies: 3',
            '',
            'This tree shows only user-defined dependencies.',
            '',
            '',
        ], self::linesOf($tester));
    }

    public function test_the_tree_flag_shows_the_dependency_tree_too(): void
    {
        $tester = $this->debugContainer(['class' => ConsoleFacade::class, '--tree' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Dependency Tree for ' . ConsoleFacade::class,
            self::separator(),
            '',
            sprintf('Class "%s" has no dependencies', ConsoleFacade::class),
            '',
            '',
        ], self::linesOf($tester));
    }

    public function test_the_tree_flag_requires_a_class_name(): void
    {
        $tester = $this->debugContainer(['--tree' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'The --tree option requires a class name argument',
            '',
        ], self::linesOf($tester));
    }

    public function test_an_unknown_class_fails(): void
    {
        $tester = $this->debugContainer(['class' => 'Does\\Not\\Exist']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'Class "Does\\Not\\Exist" does not exist',
            '',
        ], self::linesOf($tester));
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
