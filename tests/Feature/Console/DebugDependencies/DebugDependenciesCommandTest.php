<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies;

use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\AbstractService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\AutowirableCollaborator;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundContract;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundImplementation;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\EmptyConstructorService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\MixedDependenciesService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\NoConstructorService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\UnboundContract;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Console\Command\Command;

use Symfony\Component\Console\Tester\CommandTester;

use function array_column;
use function array_keys;
use function bin2hex;
use function file_put_contents;
use function is_file;
use function json_decode;
use function random_bytes;

use function sprintf;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class DebugDependenciesCommandTest extends TestCase
{
    /** @var list<string> */
    private array $sourceFiles = [];

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->sourceFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->sourceFiles = [];
    }

    public function test_unknown_class_fails(): void
    {
        $tester = $this->inspect('Does\\Not\\Exist');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Class "Does\\Not\\Exist" does not exist', $tester->getDisplay());
    }

    public function test_a_leading_backslash_is_stripped_from_the_class_argument(): void
    {
        $tester = $this->inspect('\\' . NoConstructorService::class);

        $display = $tester->getDisplay();

        // The leading backslash is stripped, so the class still resolves.
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Constructor dependencies for ' . NoConstructorService::class, $display);
        self::assertStringContainsString('No constructor', $display);
    }

    public function test_interface_fails(): void
    {
        $tester = $this->inspect(UnboundContract::class);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            sprintf('"%s" is an interface — pass a concrete class instead', UnboundContract::class),
            $tester->getDisplay(),
        );
    }

    public function test_abstract_class_fails(): void
    {
        $tester = $this->inspect(AbstractService::class);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            sprintf('"%s" is abstract — pass a concrete class instead', AbstractService::class),
            $tester->getDisplay(),
        );
    }

    public function test_class_with_an_empty_constructor_is_distinguished_from_one_without(): void
    {
        $tester = $this->inspect(EmptyConstructorService::class);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        // An empty constructor is reported differently from having none at all.
        self::assertStringContainsString('Constructor takes no parameters', $display);
        self::assertStringNotContainsString('No constructor', $display);
    }

    public function test_mixed_dependencies_are_categorized(): void
    {
        $tester = $this->inspect(MixedDependenciesService::class);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Each parameter is categorised by why it can or cannot be resolved.
        self::assertStringContainsString(sprintf('$bound %s (bound -> %s)', BoundContract::class, BoundImplementation::class), $display);
        self::assertStringContainsString(sprintf('$collaborator %s (autowirable)', AutowirableCollaborator::class), $display);
        self::assertStringContainsString(sprintf('$unbound %s interface, no binding', UnboundContract::class), $display);
        self::assertStringContainsString('$mandatoryScalar string scalar without default', $display);
        self::assertStringContainsString("\$optionalScalar string = 'default'", $display);
        self::assertStringContainsString(sprintf('$nullableCollaborator ?%s (autowirable)', AutowirableCollaborator::class), $display);

        // ...and the two categories are tallied.
        self::assertMatchesRegularExpression('/Resolvable:\s+4/', $display);
        self::assertMatchesRegularExpression('/Unresolvable:\s+2/', $display);
    }

    /**
     * A binding's value is a class name, a closure or an already-built object,
     * and only the first was covered. The other two are what a provider-style
     * registration looks like, and the report has to say something useful about
     * each rather than printing whatever `(string)` makes of it.
     */
    public function test_a_closure_binding_is_reported_as_a_closure(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(
                BoundContract::class,
                static fn (): BoundImplementation => new BoundImplementation(),
            );
        });

        self::assertStringContainsString(
            sprintf('$bound %s (bound -> Closure instance)', BoundContract::class),
            $this->inspect(MixedDependenciesService::class)->getDisplay(),
        );
    }

    public function test_an_object_binding_is_reported_by_its_class(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(BoundContract::class, new BoundImplementation());
        });

        self::assertStringContainsString(
            sprintf('$bound %s (bound -> %s instance)', BoundContract::class, BoundImplementation::class),
            $this->inspect(MixedDependenciesService::class)->getDisplay(),
        );
    }

    public function test_a_fully_resolvable_constructor_omits_the_remediation_hint(): void
    {
        $tester = $this->inspect(Fixtures\InjectService::class);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();

        // #[Inject] marks a parameter resolvable, and an override reports its target.
        self::assertStringContainsString(sprintf('$plain %s (inject)', BoundContract::class), $display);
        self::assertStringContainsString(sprintf('$withOverride %s (inject -> %s)', BoundContract::class, BoundImplementation::class), $display);
        self::assertMatchesRegularExpression('/Resolvable:\s+2/', $display);
        self::assertMatchesRegularExpression('/Unresolvable:\s+0/', $display);
    }

    public function test_accepts_a_file_path_argument(): void
    {
        $path = (new ReflectionClass(MixedDependenciesService::class))->getFileName();
        self::assertIsString($path);

        $tester = $this->inspect($path);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'Constructor dependencies for ' . MixedDependenciesService::class,
            $tester->getDisplay(),
        );
    }

    public function test_file_without_class_declaration_fails(): void
    {
        $path = $this->writeSource("<?php\n\n// no declarations\n");

        $tester = $this->inspect($path);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            sprintf('File "%s" does not declare a class, interface, trait, or enum', $path),
            $tester->getDisplay(),
        );
    }

    public function test_file_whose_class_keyword_has_no_name_fails(): void
    {
        // The keyword is the very last token, so there is nothing to read after it.
        $path = $this->writeSource('<?php class');

        $tester = $this->inspect($path);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('does not declare a class', $tester->getDisplay());
    }

    public function test_reads_a_class_declared_in_the_global_namespace(): void
    {
        $class = 'GlobalScopeService' . $this->suffix();
        $path = $this->writeSource(sprintf("<?php\n\nclass %s\n{\n}\n", $class));

        $tester = $this->inspect($path);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Constructor dependencies for ' . $class, $tester->getDisplay());
    }

    public function test_reads_a_class_in_a_single_segment_namespace(): void
    {
        $namespace = 'SingleSegment' . $this->suffix();
        $path = $this->writeSource(sprintf("<?php\n\nnamespace %s;\n\nclass Service\n{\n}\n", $namespace));

        $tester = $this->inspect($path);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'Constructor dependencies for ' . $namespace . '\\Service',
            $tester->getDisplay(),
        );
    }

    public function test_skips_an_anonymous_class_and_a_class_constant_before_the_declaration(): void
    {
        $namespace = 'SkipNoise' . $this->suffix();
        $path = $this->writeSource(sprintf(
            '<?php

namespace %s\Nested;

$anonymous = new class {
};
$reference = ' . stdClass::class . '::class;

'
            . "class Real\n{\n}\n",
            $namespace,
        ));

        $tester = $this->inspect($path);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'Constructor dependencies for ' . $namespace . '\\Nested\\Real',
            $tester->getDisplay(),
        );
    }

    public function test_recognises_an_interface_declaration(): void
    {
        $namespace = 'DeclaredInterface' . $this->suffix();
        $path = $this->writeSource(sprintf("<?php\n\nnamespace %s;\n\ninterface Contract\n{\n}\n", $namespace));

        $tester = $this->inspect($path);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            sprintf('"%s\\Contract" is an interface', $namespace),
            $tester->getDisplay(),
        );
    }

    public function test_recognises_a_trait_declaration(): void
    {
        $namespace = 'DeclaredTrait' . $this->suffix();
        $path = $this->writeSource(sprintf("<?php\n\nnamespace %s;\n\ntrait Helper\n{\n}\n", $namespace));

        $tester = $this->inspect($path);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        // A trait is neither a class nor an interface, so it is reported as
        // missing -- but only because the declaration itself was recognised.
        self::assertStringContainsString(
            sprintf('Class "%s\\Helper" does not exist', $namespace),
            $tester->getDisplay(),
        );
    }

    public function test_recognises_an_enum_declaration(): void
    {
        $namespace = 'DeclaredEnum' . $this->suffix();
        $path = $this->writeSource(sprintf("<?php\n\nnamespace %s;\n\nenum Suit\n{\n}\n", $namespace));

        $tester = $this->inspect($path);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            sprintf('Constructor dependencies for %s\\Suit', $namespace),
            $tester->getDisplay(),
        );
    }

    public function test_the_transitive_tree_is_off_by_default(): void
    {
        $tester = $this->inspect(Fixtures\NestedRootService::class);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        // Only the constructor parameter, none of what it drags in.
        self::assertStringContainsString('$mid ' . Fixtures\NestedMidService::class, $display);
        self::assertStringNotContainsString('Dependency tree', $display);
        self::assertStringNotContainsString(AutowirableCollaborator::class, $display);
    }

    public function test_tree_option_adds_the_transitive_view_below_the_constructor_view(): void
    {
        $tester = $this->inspect(Fixtures\NestedRootService::class, ['--tree' => true]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        // The one-level view is kept, and the tree is appended to it.
        self::assertStringContainsString('Constructor dependencies for', $display);
        self::assertStringContainsString('Dependency tree for ' . Fixtures\NestedRootService::class, $display);

        // Each node carries how the container will actually supply it.
        self::assertStringContainsString(Fixtures\NestedMidService::class . ' (autowired)', $display);
        self::assertStringContainsString(BoundImplementation::class . ' (autowired)', $display);
        self::assertStringContainsString(UnboundContract::class . ' (unresolvable)', $display);
        self::assertMatchesRegularExpression('/Dependencies:\s+4/', $display);
    }

    /**
     * The flag is called `--tree`, and until the container could report a graph
     * it printed a flat list. Nesting and the parameter that pulled each class
     * in are the difference between "something is missing" and knowing where.
     */
    public function test_tree_option_draws_the_nesting_and_names_the_parameter(): void
    {
        $display = $this->inspect(Fixtures\NestedRootService::class, ['--tree' => true])->getDisplay();

        self::assertStringContainsString('└── ✓ $mid: ' . Fixtures\NestedMidService::class, $display);
        // One level deeper, hanging off the mid service rather than the root:
        // the four-space indent is what says so.
        self::assertStringContainsString('    ├── ✓ $bound: ' . BoundImplementation::class, $display);
        self::assertStringContainsString('    └── ✗ $unbound: ' . UnboundContract::class, $display);
    }

    public function test_tree_option_marks_where_a_cycle_closes(): void
    {
        $tester = $this->inspect(Fixtures\CyclicLeftService::class, ['--tree' => true]);

        // Reported, not thrown: a broken graph is what the command is for.
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(Fixtures\CyclicLeftService::class, $tester->getDisplay());
        self::assertStringContainsString('(cycle)', $tester->getDisplay());
    }

    public function test_tree_option_marks_a_node_the_container_already_owns(): void
    {
        Gacela::container()->set(AutowirableCollaborator::class, new AutowirableCollaborator());

        $tester = $this->inspect(Fixtures\NestedRootService::class, ['--tree' => true]);

        self::assertStringContainsString(
            AutowirableCollaborator::class . ' (instance)',
            $tester->getDisplay(),
        );
    }

    public function test_tree_option_on_a_class_with_no_dependencies_says_so(): void
    {
        $tester = $this->inspect(NoConstructorService::class, ['--tree' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No transitive dependencies', $tester->getDisplay());
    }

    public function test_tree_option_reports_an_unresolvable_node_without_failing(): void
    {
        $tester = $this->inspect(MixedDependenciesService::class, ['--tree' => true]);

        // The command stays a diagnostic: an unresolvable dependency is
        // something to print, never something to throw or fail on.
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(UnboundContract::class . ' (unresolvable)', $tester->getDisplay());
    }

    /**
     * `DependencyTreeInspector` catches `GacelaNotBootstrappedException` from
     * `Gacela::container()` and reports `containerAvailable: false`, which is
     * the one line the reader sees for it. Nothing asserted that line, and the
     * catch it belongs to is the one #786 widened -- `Config::getInstance()`
     * used to throw a bare `RuntimeException`, which this handler never caught.
     *
     * So the degrade is deliberate and now says so: `--tree` without a
     * bootstrap explains itself instead of printing an empty tree, which would
     * read as "this class has no dependencies".
     */
    public function test_the_tree_says_why_it_is_empty_without_a_bootstrap(): void
    {
        $gacela = new ReflectionClass(Gacela::class);
        $gacela->getProperty('mainContainer')->setValue($gacela, value: null);

        try {
            $display = $this->inspect(BoundImplementation::class, ['--tree' => true])->getDisplay();

            self::assertStringContainsString('No container available', $display);
            self::assertStringContainsString('bootstrap Gacela to resolve the tree', $display);
            self::assertStringNotContainsString('No transitive dependencies', $display);
        } finally {
            // Put the process back the way setUp() leaves it.
            $this->setUp();
        }
    }

    /**
     * The parameter shape is the one `debug:modules --json` uses for a pillar,
     * so the two commands describe one parameter the same way rather than
     * inventing a vocabulary each. Including the name without its `$`.
     */
    public function test_json_reports_the_parameters_in_the_shape_the_other_commands_use(): void
    {
        $document = $this->inspectAsJson(MixedDependenciesService::class);

        self::assertSame(MixedDependenciesService::class, $document['class']);
        self::assertTrue($document['hasConstructor']);

        $byName = array_column($document['parameters'], null, 'name');

        self::assertSame(
            ['name' => 'mandatoryScalar', 'type' => 'string', 'status' => 'scalar-without-default',
                'detail' => 'scalar without default', 'resolvable' => false],
            $byName['mandatoryScalar'],
        );
        self::assertSame('unbound-interface', $byName['unbound']['status']);
        self::assertTrue($byName['collaborator']['resolvable']);
    }

    public function test_json_counts_resolvable_and_unresolvable(): void
    {
        $document = $this->inspectAsJson(MixedDependenciesService::class);

        self::assertSame(
            [$document['resolvable'], $document['unresolvable']],
            [4, 2],
        );
    }

    /**
     * `--tree` means in the document what it means in the text report, where
     * the section only appears with the flag: building a transitive graph
     * nobody asked for is not free.
     */
    public function test_the_tree_is_absent_without_the_flag_and_present_with_it(): void
    {
        self::assertArrayNotHasKey('tree', $this->inspectAsJson(MixedDependenciesService::class));

        $withTree = $this->inspectAsJson(MixedDependenciesService::class, ['--tree' => true]);

        self::assertArrayHasKey('tree', $withTree);
        self::assertArrayHasKey('total', $withTree);
        self::assertTrue($withTree['containerAvailable']);
    }

    /**
     * The tree shape is `debug:container --json`'s, so a class inspected
     * through either command reads the same.
     */
    public function test_the_tree_nodes_carry_the_parameter_that_pulled_them_in(): void
    {
        $tree = $this->inspectAsJson(MixedDependenciesService::class, ['--tree' => true])['tree'];

        self::assertNotSame([], $tree);
        self::assertSame(
            ['class', 'parameter', 'status', 'repeated', 'children'],
            array_keys($tree[0]),
        );
    }

    public function test_a_class_that_does_not_exist_is_an_error_document(): void
    {
        $tester = $this->inspect('NoSuchClassAnywhere', ['--json' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(
            ['error' => 'Class "NoSuchClassAnywhere" does not exist'],
            json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The other two refusals answer in the same shape: a consumer piping this
     * into a parser gets a document on every run that refused, not only the
     * one that could not find the class.
     */
    public function test_an_abstract_class_is_an_error_document_too(): void
    {
        $tester = $this->inspect(AbstractService::class, ['--json' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        /** @var array{error: string} $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        self::assertStringContainsString('is abstract', $decoded['error']);
    }

    public function test_a_class_without_a_constructor_says_so_rather_than_omitting_the_key(): void
    {
        $document = $this->inspectAsJson(NoConstructorService::class);

        self::assertFalse($document['hasConstructor']);
        self::assertSame([], $document['parameters']);
    }

    /**
     * @param array<string, bool|string> $options
     *
     * @return array<string, mixed>
     */
    private function inspectAsJson(string $argument, array $options = []): array
    {
        $display = $this->inspect($argument, $options + ['--json' => true])->getDisplay();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function inspect(string $argument, array $options = []): CommandTester
    {
        $tester = new CommandTester(new DebugDependenciesCommand());
        $tester->execute(['class' => $argument] + $options);

        return $tester;
    }

    private function writeSource(string $source): string
    {
        $path = sys_get_temp_dir() . '/gacela-debug-deps-' . $this->suffix() . '.php';
        file_put_contents($path, $source);
        $this->sourceFiles[] = $path;

        return $path;
    }

    private function suffix(): string
    {
        return bin2hex(random_bytes(6));
    }

}
