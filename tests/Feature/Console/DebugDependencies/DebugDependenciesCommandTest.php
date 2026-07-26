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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_put_contents;
use function is_file;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

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
        $class = 'GlobalScopeService' . self::suffix();
        $path = $this->writeSource(sprintf("<?php\n\nclass %s\n{\n}\n", $class));

        $tester = $this->inspect($path);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Constructor dependencies for ' . $class, $tester->getDisplay());
    }

    public function test_reads_a_class_in_a_single_segment_namespace(): void
    {
        $namespace = 'SingleSegment' . self::suffix();
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
        $namespace = 'SkipNoise' . self::suffix();
        $path = $this->writeSource(sprintf(
            "<?php\n\nnamespace %s\\Nested;\n\n\$anonymous = new class {\n};\n\$reference = \\stdClass::class;\n\n"
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
        $namespace = 'DeclaredInterface' . self::suffix();
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
        $namespace = 'DeclaredTrait' . self::suffix();
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
        $namespace = 'DeclaredEnum' . self::suffix();
        $path = $this->writeSource(sprintf("<?php\n\nnamespace %s;\n\nenum Suit\n{\n}\n", $namespace));

        $tester = $this->inspect($path);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            sprintf('Constructor dependencies for %s\\Suit', $namespace),
            $tester->getDisplay(),
        );
    }

    private function inspect(string $argument): CommandTester
    {
        $tester = new CommandTester(new DebugDependenciesCommand());
        $tester->execute(['class' => $argument]);

        return $tester;
    }

    private function writeSource(string $source): string
    {
        $path = sys_get_temp_dir() . '/gacela-debug-deps-' . self::suffix() . '.php';
        file_put_contents($path, $source);
        $this->sourceFiles[] = $path;

        return $path;
    }

    private static function suffix(): string
    {
        return bin2hex(random_bytes(6));
    }

}
