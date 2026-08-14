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

    /**
     * Labelled "Process Memory" since container 2.0 renamed the field it reads.
     * The number always covered the whole PHP process rather than this
     * container, and the old label read as the opposite.
     */
    public function test_the_process_memory_is_reported(): void
    {
        $tester = $this->debugContainer([]);

        self::assertMatchesRegularExpression(
            '/Process Memory: \d+(\.\d+)? [KMG]?B/',
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

    /**
     * A binding registers no service, so the "empty" hint keyed on that one
     * counter contradicted the line above it: `User Bindings: 1` and "Container
     * is empty" in the same output. Adding a binding and running this command to
     * check it landed is the likeliest reason to run it at all.
     */
    public function test_a_container_holding_only_a_binding_is_not_reported_as_empty(): void
    {
        $tester = $this->debugContainer([], static function (GacelaConfig $config): void {
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });

        $display = $tester->getDisplay();

        self::assertMatchesRegularExpression('/Registered Services: 0\b/', $display);
        self::assertMatchesRegularExpression('/User Bindings: 1\b/', $display);
        self::assertStringNotContainsString('Container is empty', $display);
    }

    /**
     * Counting them answers "how many", which is not the question. What a
     * binding resolves to is the thing being debugged, and `debug:module`
     * already prints it -- from the same facade method this now reads.
     */
    public function test_the_bindings_are_named_and_not_only_counted(): void
    {
        $tester = $this->debugContainer([], static function (GacelaConfig $config): void {
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });

        self::assertStringContainsString(
            BoundContract::class . ' => ' . BoundImplementation::class,
            $tester->getDisplay(),
        );
    }

    /**
     * Asserted on the arrow rather than on a heading: `User Bindings: 0` is
     * already in the output, so any heading containing "Bindings" matches it.
     */
    public function test_an_empty_container_lists_no_bindings(): void
    {
        $display = $this->debugContainer([])->getDisplay();

        self::assertStringContainsString('Container is empty', $display);
        self::assertStringNotContainsString(' => ', $display);
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
     * `--json` on every command that can emit it was the point of the sweep
     * that unified the two spellings; this one still answered `The "--json"
     * option does not exist.`
     */
    public function test_json_reports_the_statistics_as_a_document(): void
    {
        $document = $this->debugContainerAsJson([], static function (GacelaConfig $config): void {
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });

        self::assertSame(1, $document['stats']['bindings']);
        self::assertSame(0, $document['stats']['registeredServices']);
        self::assertSame([BoundContract::class => BoundImplementation::class], $document['bindings']);
    }

    /**
     * Bytes rather than the "10 MB" the text prints: a document is compared and
     * charted, and a formatted string is neither.
     */
    public function test_the_process_memory_is_reported_in_bytes(): void
    {
        $document = $this->debugContainerAsJson([]);

        self::assertIsInt($document['stats']['processMemoryBytes']);
        self::assertGreaterThan(0, $document['stats']['processMemoryBytes']);
    }

    /**
     * An empty map stays an object. Encoding an empty PHP array yields `[]`,
     * so a consumer indexing `bindings` by name would meet a list on exactly
     * the runs where there is nothing to index.
     */
    public function test_an_empty_binding_map_stays_an_object(): void
    {
        self::assertStringContainsString('"bindings": {}', $this->debugContainer(['--json' => true])->getDisplay());
    }

    public function test_json_reports_the_dependency_tree_with_its_shape_kept(): void
    {
        $document = $this->debugContainerAsJson(['class' => TreeRoot::class]);

        self::assertSame(TreeRoot::class, $document['class']);
        self::assertTrue($document['containerAvailable']);

        $tree = $document['tree'];
        self::assertCount(2, $tree);
        self::assertSame(TreeMiddle::class, $tree[0]['class']);
        self::assertSame('middle', $tree[0]['parameter']);
        self::assertSame('autowired', $tree[0]['status']);
        self::assertFalse($tree[0]['repeated']);
        self::assertSame(TreeLeaf::class, $tree[0]['children'][0]['class']);
    }

    /**
     * `total` counts distinct classes and `tree` keeps the shape, so a class
     * two parents ask for is one in the count and twice in the tree. Both
     * answers are wanted, and a document carrying only one of them would be
     * the wrong one for somebody.
     */
    public function test_total_counts_a_shared_dependency_once_while_the_tree_repeats_it(): void
    {
        $document = $this->debugContainerAsJson(['class' => TreeRoot::class]);

        self::assertSame(2, $document['total']);
        self::assertSame(
            [TreeMiddle::class, TreeLeaf::class],
            [$document['tree'][0]['class'], $document['tree'][1]['class']],
        );
    }

    public function test_an_unknown_class_is_an_error_document_rather_than_a_line_of_prose(): void
    {
        $tester = $this->debugContainer(['class' => 'NoSuchClassAnywhere', '--json' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(
            ['error' => 'class "NoSuchClassAnywhere" does not exist'],
            json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function test_the_tree_flag_without_a_class_is_an_error_document_too(): void
    {
        $tester = $this->debugContainer(['--tree' => true, '--json' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertIsArray(json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * The precedence the text report already has, kept in the document: a
     * `--stats` run reports statistics whatever else was passed.
     */
    public function test_stats_takes_precedence_over_the_class_argument_in_json_too(): void
    {
        $document = $this->debugContainerAsJson(['class' => TreeRoot::class, '--stats' => true]);

        self::assertArrayHasKey('stats', $document);
        self::assertArrayNotHasKey('tree', $document);
    }

    /**
     * @param array<string, bool|string> $input
     * @param null|Closure(GacelaConfig):void $configFn
     *
     * @return array<string, mixed>
     */
    private function debugContainerAsJson(array $input, ?Closure $configFn = null): array
    {
        $display = $this->debugContainer($input + ['--json' => true], $configFn)->getDisplay();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, bool|string> $input
     * @param null|Closure(GacelaConfig):void $configFn
     */
    private function debugContainer(array $input, ?Closure $configFn = null): CommandTester
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            if ($configFn instanceof Closure) {
                $configFn($config);
            }
        });

        $tester = new CommandTester(new DebugContainerCommand());
        $tester->execute($input);

        return $tester;
    }
}

final class TreeLeaf
{
}

final class TreeMiddle
{
    public function __construct(
        public readonly TreeLeaf $leaf,
    ) {
    }
}

final class TreeRoot
{
    public function __construct(
        public readonly TreeMiddle $middle,
        public readonly TreeLeaf $leaf,
    ) {
    }
}
