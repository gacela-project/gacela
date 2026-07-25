<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig;

use Closure;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\BaseImplementation;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\CyclicA;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\CyclicB;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\CyclicContract;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\InvokableBinding;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\MismatchedImplementation;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\NeedsMandatoryScalar;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\OtherContract;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\SomeContract;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\SomeImplementation;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\ThrowsColonPackedCycle;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\ThrowsUnparseableCycle;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\UnrelatedImplementation;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_slice;
use function bin2hex;
use function explode;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function rtrim;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function unlink;

final class ValidateConfigCommandTest extends TestCase
{
    /**
     * A class name nothing can ever autoload, used to trigger the
     * "binding key/value does not exist" branches.
     *
     * @var class-string
     */
    private const MISSING_CLASS = 'GacelaTest\\Feature\\Console\\ValidateConfig\\Fixtures\\NeverDeclared';

    /**
     * A class name whose autoloading blows up, used to trigger the branch that
     * turns an unexpected failure into a reported error.
     *
     * @var class-string
     */
    private const EXPLODING_CLASS = 'GacelaTest\\Feature\\Console\\ValidateConfig\\Fixtures\\ExplodingAutoload';

    private const AUTOLOAD_FAILURE = 'autoloading blew up';

    private string $appRoot = '';

    /** @var list<callable(string):void> */
    private array $registeredAutoloaders = [];

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/gacela-validate-config-' . bin2hex(random_bytes(4));
        mkdir($this->appRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredAutoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $this->registeredAutoloaders = [];

        $gacelaPhp = $this->appRoot . '/gacela.php';
        if (is_file($gacelaPhp)) {
            unlink($gacelaPhp);
        }

        if (is_dir($this->appRoot)) {
            rmdir($this->appRoot);
        }
    }

    public function test_help_does_not_imply_a_missing_gacela_php_is_flagged(): void
    {
        $help = (new ValidateConfigCommand())->getHelp();

        // A missing gacela.php stays silent, so the help must not present its
        // existence as a pass/fail check.
        self::assertStringNotContainsString('Existence of gacela.php', $help);
        self::assertStringContainsString('absence is not an error', $help);
    }

    public function test_an_empty_configuration_is_valid_and_stays_silent_about_gacela_php(): void
    {
        $tester = $this->validate(static function (): void {
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Validating Gacela Configuration',
            self::separator(),
            '',
            'Checking bindings...',
            '  No bindings configured',
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✓ Configuration is valid!',
            '',
            '',
        ], self::linesOf($tester->getDisplay()));
    }

    public function test_reports_the_gacela_php_file_when_the_project_root_has_one(): void
    {
        file_put_contents(
            $this->appRoot . '/gacela.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn static function (): void {\n};\n",
        );

        $tester = $this->validate(static function (): void {
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Validating Gacela Configuration',
            self::separator(),
            '',
            sprintf('✓ Configuration file found: %s/gacela.php', $this->appRoot),
            '',
            'Checking bindings...',
        ], array_slice(self::linesOf($tester->getDisplay()), 0, 7));
    }

    public function test_a_single_compatible_binding_is_reported_in_the_singular(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, SomeImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 1 binding',
            '',
            '  ✓ ' . SomeContract::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✓ Configuration is valid!',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_several_bindings_are_reported_in_the_plural(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, SomeImplementation::class);
            $config->addBinding(OtherContract::class, MismatchedImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 2 bindings',
            '',
            '  ✓ ' . SomeContract::class,
            '  ✓ ' . OtherContract::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✓ Configuration is valid!',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_a_binding_to_the_key_itself_is_compatible(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, SomeImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 1 binding',
            '',
            '  ✓ ' . SomeImplementation::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✓ Configuration is valid!',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_reports_a_binding_key_that_does_not_exist_and_keeps_going(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(self::MISSING_CLASS, SomeImplementation::class);
            $config->addBinding(SomeContract::class, SomeImplementation::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 2 bindings',
            '',
            '  ✗ Binding key does not exist: ' . self::MISSING_CLASS,
            '  ✓ ' . SomeContract::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✗ Validation failed with errors',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_reports_a_binding_value_class_that_does_not_exist(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, self::MISSING_CLASS);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 1 binding',
            '',
            sprintf(
                '  ✗ Binding value class does not exist: %s -> %s',
                SomeContract::class,
                self::MISSING_CLASS,
            ),
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✗ Validation failed with errors',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_reports_a_binding_value_class_that_does_not_exist_and_keeps_going(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, self::MISSING_CLASS);
            $config->addBinding(CyclicContract::class, CyclicA::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 2 bindings',
            '',
            sprintf(
                '  ✗ Binding value class does not exist: %s -> %s',
                SomeContract::class,
                self::MISSING_CLASS,
            ),
            '  ✓ ' . CyclicContract::class,
            '',
            'Checking for circular dependencies...',
            '  ✗ Circular dependency detected: ' . CyclicContract::class,
            sprintf('      chain: %s -> %s -> %s', CyclicB::class, CyclicA::class, CyclicB::class),
            '',
            '',
            self::separator(),
            '✗ Validation failed with errors',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_warns_about_an_incompatible_binding_value_and_describes_its_type_chain(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, MismatchedImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 1 binding',
            '',
            sprintf(
                '  ⚠ Warning: Binding value may not be compatible with key: %s -> %s',
                SomeContract::class,
                MismatchedImplementation::class,
            ),
            '      expected interface: ' . SomeContract::class,
            sprintf(
                '      actual:       %s | extends %s | implements %s',
                MismatchedImplementation::class,
                BaseImplementation::class,
                OtherContract::class,
            ),
            sprintf(
                '      hint:         make %s extend or implement %s',
                MismatchedImplementation::class,
                SomeContract::class,
            ),
            '  ✓ ' . SomeContract::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '⚠ Validation completed with warnings',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_reports_the_expected_kind_as_class_when_the_key_is_a_class(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(BaseImplementation::class, UnrelatedImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            '      expected class: ' . BaseImplementation::class,
            $tester->getDisplay(),
        );
    }

    public function test_reports_an_object_binding_that_is_not_an_instance_of_its_key_and_keeps_going(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, new UnrelatedImplementation());
            $config->addBinding(SomeContract::class, SomeImplementation::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 2 bindings',
            '',
            '  ✗ Binding object is not instance of key: ' . SomeImplementation::class,
            '  ✓ ' . SomeContract::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✗ Validation failed with errors',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_accepts_an_object_binding_that_is_an_instance_of_its_key(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, new SomeImplementation());
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 1 binding',
            '',
            '  ✓ ' . SomeImplementation::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✓ Configuration is valid!',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_accepts_a_callable_object_binding_whatever_its_key(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, new InvokableBinding());
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 1 binding',
            '',
            '  ✓ ' . SomeImplementation::class,
            '',
            'Checking for circular dependencies...',
            '  ✓ No circular dependencies detected',
            '',
            '',
            self::separator(),
            '✓ Configuration is valid!',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_warns_when_a_valid_binding_cannot_be_resolved(): void
    {
        // The object binding comes first so the string binding behind it proves
        // the loop skips non-string values instead of stopping at them.
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, new SomeImplementation());
            $config->addBinding(SomeContract::class, NeedsMandatoryScalar::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 2 bindings',
            '',
            '  ✓ ' . SomeImplementation::class,
            '  ✓ ' . SomeContract::class,
            '',
            'Checking for circular dependencies...',
        ], array_slice(self::bindingsSectionOf($tester), 0, 7));

        $display = $tester->getDisplay();
        self::assertStringContainsString(
            '  ⚠ Warning: Could not resolve binding: ' . SomeContract::class,
            $display,
        );
        self::assertStringContainsString('⚠ Validation completed with warnings', $display);
    }

    public function test_reports_a_real_circular_dependency_with_its_chain(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(CyclicContract::class, CyclicA::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            'Checking bindings...',
            '  Found 1 binding',
            '',
            '  ✓ ' . CyclicContract::class,
            '',
            'Checking for circular dependencies...',
            '  ✗ Circular dependency detected: ' . CyclicContract::class,
            sprintf('      chain: %s -> %s -> %s', CyclicB::class, CyclicA::class, CyclicB::class),
            '',
            '',
            self::separator(),
            '✗ Validation failed with errors',
            '',
            '',
        ], self::bindingsSectionOf($tester));
    }

    public function test_prints_a_cycle_headline_without_a_separator_verbatim(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, ThrowsUnparseableCycle::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            '      chain: ' . ThrowsUnparseableCycle::HEADLINE,
            $tester->getDisplay(),
        );
    }

    public function test_keeps_the_whole_chain_when_it_starts_right_after_the_colon(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, ThrowsColonPackedCycle::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            '      chain: ' . ThrowsColonPackedCycle::CHAIN,
            $tester->getDisplay(),
        );
    }

    public function test_reports_a_binding_whose_class_cannot_even_be_autoloaded(): void
    {
        $this->registerExplodingAutoloader();

        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, self::EXPLODING_CLASS);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        $display = $tester->getDisplay();
        self::assertStringContainsString(
            '  Error validating bindings: ' . self::AUTOLOAD_FAILURE,
            $display,
        );
        self::assertStringNotContainsString('  ✓ ' . SomeContract::class, $display);
        self::assertStringContainsString(
            sprintf(
                '  ⚠ Warning: Could not resolve binding: %s (%s)',
                SomeContract::class,
                self::AUTOLOAD_FAILURE,
            ),
            $display,
        );
        self::assertStringContainsString('✗ Validation failed with errors', $display);
    }

    /**
     * @param Closure(GacelaConfig):void $configFn
     */
    private function validate(Closure $configFn): CommandTester
    {
        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $configFn($config);
        });

        $tester = new CommandTester(new ValidateConfigCommand());
        $tester->execute([]);

        return $tester;
    }

    private function registerExplodingAutoloader(): void
    {
        $autoloader = static function (string $class): void {
            if ($class === self::EXPLODING_CLASS) {
                throw new RuntimeException(self::AUTOLOAD_FAILURE);
            }
        };

        spl_autoload_register($autoloader, true, true);
        $this->registeredAutoloaders[] = $autoloader;
    }

    /**
     * Everything the command prints after the "Validating Gacela Configuration"
     * banner, which is asserted on its own in the empty-configuration test.
     *
     * @return list<string>
     */
    private static function bindingsSectionOf(CommandTester $tester): array
    {
        return array_slice(self::linesOf($tester->getDisplay()), 4);
    }

    private static function separator(): string
    {
        return str_repeat('=', 60);
    }

    /**
     * @return list<string>
     */
    private static function linesOf(string $display): array
    {
        return array_map(
            static fn (string $line): string => rtrim($line),
            explode("\n", $display),
        );
    }
}
