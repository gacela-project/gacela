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
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\SideEffectingImplementation;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\SomeContract;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\SomeImplementation;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\UnrelatedImplementation;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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
        self::assertStringContainsString('No bindings configured', $tester->getDisplay());
        self::assertSame([
            '✓ No circular dependencies detected',
            '✓ Configuration is valid!',
        ], $this->verdictLinesOf($tester));
    }

    public function test_reports_the_gacela_php_file_when_the_project_root_has_one(): void
    {
        file_put_contents(
            $this->appRoot . '/gacela.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn static function (): void {\n};\n",
        );

        $tester = $this->validate(static function (): void {
        });

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // The path is joined with DIRECTORY_SEPARATOR, so assert on the report
        // and the file name rather than on the separator the host uses.
        self::assertStringContainsString('Configuration file found:', $display);
        self::assertStringContainsString('gacela.php', $display);
    }

    public function test_a_single_compatible_binding_is_reported_in_the_singular(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, SomeImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Found 1 binding', $tester->getDisplay());
        self::assertSame([
            '✓ ' . SomeContract::class,
            '✓ No circular dependencies detected',
            '✓ Configuration is valid!',
        ], $this->verdictLinesOf($tester));
    }

    public function test_several_bindings_are_reported_in_the_plural(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, SomeImplementation::class);
            $config->addBinding(OtherContract::class, MismatchedImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Found 2 bindings', $tester->getDisplay());
        self::assertSame([
            '✓ ' . SomeContract::class,
            '✓ ' . OtherContract::class,
            '✓ No circular dependencies detected',
            '✓ Configuration is valid!',
        ], $this->verdictLinesOf($tester));
    }

    public function test_a_binding_to_the_key_itself_is_compatible(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, SomeImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '✓ ' . SomeImplementation::class,
            '✓ No circular dependencies detected',
            '✓ Configuration is valid!',
        ], $this->verdictLinesOf($tester));
    }

    public function test_accepts_an_arbitrary_string_service_id(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding('original-service', SomeImplementation::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '✓ original-service',
            '✓ No circular dependencies detected',
            '✓ Configuration is valid!',
        ], $this->verdictLinesOf($tester));
    }

    public function test_reports_a_binding_value_class_that_does_not_exist(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, self::MISSING_CLASS);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            sprintf('✗ Binding value class does not exist: %s -> %s', SomeContract::class, self::MISSING_CLASS),
            '✓ No circular dependencies detected',
            '✗ Validation failed with errors',
        ], $this->verdictLinesOf($tester));
    }

    public function test_reports_a_binding_value_class_that_does_not_exist_and_keeps_going(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, self::MISSING_CLASS);
            $config->addBinding(CyclicContract::class, CyclicA::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        // An unloadable value does not stop the scan, so the cycle behind it is
        // still found and reported with the chain that produced it.
        self::assertSame([
            sprintf('✗ Binding value class does not exist: %s -> %s', SomeContract::class, self::MISSING_CLASS),
            '✓ ' . CyclicContract::class,
            '✗ Circular dependency detected: ' . CyclicContract::class,
            '✗ Validation failed with errors',
        ], $this->verdictLinesOf($tester));

        self::assertStringContainsString(
            sprintf('chain: %s -> %s -> %s', CyclicA::class, CyclicB::class, CyclicA::class),
            $tester->getDisplay(),
        );
    }

    public function test_warns_about_an_incompatible_binding_value_and_describes_its_type_chain(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, MismatchedImplementation::class);
        });

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            sprintf(
                '⚠ Warning: Binding value may not be compatible with key: %s -> %s',
                SomeContract::class,
                MismatchedImplementation::class,
            ),
            // No `✓` for this key: the tick says the binding had nothing to
            // report, so printing it under a warning about the same key read as
            // the warning being withdrawn. The two error paths already skip it.
            '✓ No circular dependencies detected',
            '⚠ Validation completed with warnings',
        ], $this->verdictLinesOf($tester));

        // The warning explains what was expected, what the value actually is, and
        // how to fix it.
        self::assertStringContainsString('expected interface: ' . SomeContract::class, $display);
        self::assertStringContainsString(
            sprintf('%s | extends %s | implements %s', MismatchedImplementation::class, BaseImplementation::class, OtherContract::class),
            $display,
        );
        self::assertStringContainsString(
            sprintf('make %s extend or implement %s', MismatchedImplementation::class, SomeContract::class),
            $display,
        );
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
            '✗ Binding object is not instance of key: ' . SomeImplementation::class,
            '✓ ' . SomeContract::class,
            '✓ No circular dependencies detected',
            '✗ Validation failed with errors',
        ], $this->verdictLinesOf($tester));
    }

    public function test_accepts_an_object_binding_that_is_an_instance_of_its_key(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, new SomeImplementation());
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '✓ ' . SomeImplementation::class,
            '✓ No circular dependencies detected',
            '✓ Configuration is valid!',
        ], $this->verdictLinesOf($tester));
    }

    public function test_accepts_a_callable_object_binding_whatever_its_key(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeImplementation::class, new InvokableBinding());
        });

        // A callable value is accepted whatever the key is, because it is resolved
        // at runtime rather than being an instance of it now.
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '✓ ' . SomeImplementation::class,
            '✓ No circular dependencies detected',
            '✓ Configuration is valid!',
        ], $this->verdictLinesOf($tester));
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

        // Both bindings pass the compatibility check; static graph validation
        // reports the unsatisfied scalar without constructing either service.
        $verdicts = $this->verdictLinesOf($tester);

        self::assertSame('✓ ' . SomeImplementation::class, $verdicts[0]);
        self::assertSame('✓ ' . SomeContract::class, $verdicts[1]);
        self::assertStringStartsWith(
            '⚠ Warning: Could not resolve binding: ' . SomeContract::class,
            $verdicts[2],
        );
        self::assertSame('✓ No circular dependencies detected', $verdicts[3]);
        self::assertSame('⚠ Validation completed with warnings', $verdicts[4]);
    }

    public function test_reports_a_real_circular_dependency_with_its_chain(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(CyclicContract::class, CyclicA::class);
        });

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            '✓ ' . CyclicContract::class,
            '✗ Circular dependency detected: ' . CyclicContract::class,
            '✗ Validation failed with errors',
        ], $this->verdictLinesOf($tester));

        // The chain that produced the cycle is printed under the error.
        self::assertStringContainsString(
            sprintf('chain: %s -> %s -> %s', CyclicA::class, CyclicB::class, CyclicA::class),
            $tester->getDisplay(),
        );
    }

    public function test_validation_does_not_construct_services_or_invoke_factories(): void
    {
        SideEffectingImplementation::$constructionCount = 0;
        $factoryCalls = 0;

        $tester = $this->validate(static function (GacelaConfig $config) use (&$factoryCalls): void {
            $config->addBinding(SomeContract::class, SideEffectingImplementation::class);
            $config->addBinding('runtime-service', static function () use (&$factoryCalls): SomeImplementation {
                ++$factoryCalls;

                return new SomeImplementation();
            });
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(0, SideEffectingImplementation::$constructionCount);
        self::assertSame(0, $factoryCalls);
        self::assertStringContainsString(
            'Runtime factory not executed; static graph skipped: runtime-service',
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
                '  ⚠ Warning: Could not inspect binding: %s (%s)',
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
    /**
     * `doctor --strict` has always offered this bargain: a warning is worth
     * reading but not worth failing a build over, until a project says it is.
     * This command reported warnings and exited SUCCESS with no way to say so,
     * leaving grep as the only way to act on one -- which is what an exit code
     * exists to avoid.
     */
    public function test_a_warning_fails_a_strict_run(): void
    {
        $tester = $this->validate(
            static function (GacelaConfig $config): void {
                $config->addBinding(SomeContract::class, MismatchedImplementation::class);
            },
            ['--strict' => true],
        );

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('⚠ Validation completed with warnings', $tester->getDisplay());
    }

    public function test_a_clean_run_still_passes_under_strict(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
        }, ['--strict' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * `doctor` reports as a document, and this command could not: the
     * validators wrote straight to the output and returned two booleans, so
     * there was nothing to serialise -- the messages *were* the report. A job
     * wanting to know which binding failed, or to repeat the reason into a
     * review comment, had to parse the prose.
     *
     * The two commands do not overlap either: only `doctor` checks config
     * sources, and only this one checks binding cycles, so a job wanting both
     * runs both and could parse only one of them.
     */
    public function test_json_names_the_check_that_failed_and_why(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, self::MISSING_CLASS);
        }, ['--json' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());

        $report = $this->decode($tester);

        self::assertSame('error', $report['status']);
        self::assertSame('error', $this->check($report, 'bindings')['status']);
        self::assertSame(
            sprintf('Binding value class does not exist: %s -> %s', SomeContract::class, self::MISSING_CLASS),
            $this->check($report, 'bindings')['findings'][0]['message'],
        );

        // The other checks answer for themselves rather than being dragged down
        // with it -- which is the whole reason to report per check.
        self::assertSame('ok', $this->check($report, 'circular-dependencies')['status']);
    }

    /**
     * The lines under a warning are what makes it actionable, and dropping them
     * would leave the document saying less than the prose it replaces.
     */
    public function test_json_carries_the_detail_lines_the_text_report_prints(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, MismatchedImplementation::class);
        }, ['--json' => true]);

        $finding = $this->check($this->decode($tester), 'bindings')['findings'][0];

        self::assertSame('warn', $finding['status']);
        self::assertSame([
            'expected interface: ' . SomeContract::class,
            sprintf(
                'actual:       %s | extends %s | implements %s',
                MismatchedImplementation::class,
                BaseImplementation::class,
                OtherContract::class,
            ),
            sprintf(
                'hint:         make %s extend or implement %s',
                MismatchedImplementation::class,
                SomeContract::class,
            ),
        ], $finding['details']);
    }

    /**
     * A document with a banner printed above it is not a document.
     */
    public function test_json_emits_nothing_but_the_document(): void
    {
        $tester = $this->validate(static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, SomeImplementation::class);
        }, ['--json' => true]);

        $display = $tester->getDisplay();

        self::assertStringStartsWith('{', ltrim($display));
        self::assertStringNotContainsString('Validating Gacela Configuration', $display);
        self::assertStringNotContainsString('Checking bindings...', $display);
    }

    /**
     * `--json` and `--format=json` are two spellings of one request: a reader
     * who learned the one this command did not have met `The "--json" option
     * does not exist.`, which is what made every command accept both.
     */
    public function test_the_json_flag_and_the_format_option_produce_one_document(): void
    {
        $binding = static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, MismatchedImplementation::class);
        };

        self::assertSame(
            $this->validate($binding, ['--json' => true])->getDisplay(),
            $this->validate($binding, ['--format' => 'json'])->getDisplay(),
        );
    }

    /**
     * The exit code is what a job acts on, and reading the report a different
     * way is not a reason for a different verdict.
     */
    public function test_json_reaches_the_same_verdict_as_the_text_report(): void
    {
        $warning = static function (GacelaConfig $config): void {
            $config->addBinding(SomeContract::class, MismatchedImplementation::class);
        };

        self::assertSame(Command::SUCCESS, $this->validate($warning, ['--json' => true])->getStatusCode());
        self::assertSame(
            Command::FAILURE,
            $this->validate($warning, ['--json' => true, '--strict' => true])->getStatusCode(),
        );
    }

    /**
     * @return array{status: string, configFile: string, checks: list<array{name: string, status: string, findings: list<array{status: string, message: string, details: list<string>}>}>}
     */
    private function decode(CommandTester $tester): array
    {
        /** @var array{status: string, configFile: string, checks: list<array{name: string, status: string, findings: list<array{status: string, message: string, details: list<string>}>}>} $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array{checks: list<array{name: string, status: string, findings: list<array{status: string, message: string, details: list<string>}>}>} $report
     *
     * @return array{name: string, status: string, findings: list<array{status: string, message: string, details: list<string>}>}
     */
    private function check(array $report, string $name): array
    {
        foreach ($report['checks'] as $check) {
            if ($check['name'] === $name) {
                return $check;
            }
        }

        self::fail(sprintf('no check named "%s" in the report', $name));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function validate(Closure $configFn, array $input = []): CommandTester
    {
        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            $configFn($config);
        });

        $tester = new CommandTester(new ValidateConfigCommand());
        $tester->execute($input);

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
     * The ✓/⚠/✗ lines only: the verdict reached for each binding, for the cycle
     * check, and overall. Keeps the assertions off the banner, the section
     * headings and the indentation, none of which carry a verdict.
     *
     * @return list<string>
     */
    private function verdictLinesOf(CommandTester $tester): array
    {
        $verdicts = [];
        foreach ($this->linesOf($tester->getDisplay()) as $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^[✓⚠✗] /u', $trimmed) === 1) {
                $verdicts[] = $trimmed;
            }
        }

        return $verdicts;
    }

    /**
     * @return list<string>
     */
    private function linesOf(string $display): array
    {
        return array_map(
            rtrim(...),
            explode("\n", $display),
        );
    }
}
