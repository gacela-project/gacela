<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp;

use Gacela\Console\Infrastructure\Command\CacheClearCommand;
use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Console\Infrastructure\Command\DebugConfigCommand;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Console\Infrastructure\Command\DebugEventsCommand;
use Gacela\Console\Infrastructure\Command\DebugGraphCommand;
use Gacela\Console\Infrastructure\Command\DebugModuleCommand;
use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Console\Infrastructure\Command\DebugProvidesCommand;
use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Console\Infrastructure\Command\DtoGenerateCommand;
use Gacela\Console\Infrastructure\Command\IdeMetaCommand;
use Gacela\Console\Infrastructure\Command\InitCommand;
use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Console\Infrastructure\Command\MigrateServiceMapCommand;
use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Console\Infrastructure\Command\StubsPublishCommand;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameCachedFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameValidCandidateFoundEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use Gacela\Framework\Profiler\Profiler;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceRecord;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Support\RecordingEventDispatcher;
use GacelaTest\Feature\ReferenceApp\Support\ReferenceApp;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function count;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function sprintf;
use function str_starts_with;

/**
 * Every command Gacela ships, run against the reference application.
 *
 * The exit code is half of what a command is for -- a CI job runs `doctor
 * --strict` and reads nothing else -- so each test below asserts the code and
 * one fact of the output. Together they answer the question no per-feature
 * fixture can: does the whole toolchain still work on an application that uses
 * everything at once.
 *
 * The two analysers are the exception. They are subprocesses, and running them
 * from the feature suite would make it minutes slower for an answer the
 * integration suite already gives -- see
 * {@see \GacelaTest\Integration\PHPStan\ReferenceAppTest} and
 * {@see \GacelaTest\Integration\Psalm\ReferenceAppTest}, which analyse this
 * application at level max / errorLevel 1 with the shipped rules, including the
 * three that are opt-in. What is checked here is that those two configs still
 * point at the application.
 */
final class InvoicingToolingTest extends TestCase
{
    /**
     * `make:module` and `make:file` write relative to the process working
     * directory, so their output lands in the repository's own scratch
     * directory rather than anywhere near the application.
     */
    private const SCAFFOLD_MODULE = 'ReferenceAppScaffold';

    private const SCAFFOLD_FILE_MODULE = 'ReferenceAppScaffoldFile';

    private string $cacheDir = '';

    /** @var list<string> */
    private array $tempDirectories = [];

    protected function setUp(): void
    {
        $this->cacheDir = $this->createTempDirectory('tooling-cache');
        putenv('GACELA_CACHE_DIR=' . $this->cacheDir);
    }

    protected function tearDown(): void
    {
        ReferenceApp::reset();
        Profiler::getInstance()->reset();
        Profiler::getInstance()->disable();

        foreach ($this->tempDirectories as $directory) {
            ReferenceApp::removeTempDirectory($directory);
        }

        $this->tempDirectories = [];

        foreach ([self::SCAFFOLD_MODULE, self::SCAFFOLD_FILE_MODULE] as $module) {
            $this->removeScaffoldOutput($module);
        }
    }

    public function test_doctor_has_nothing_to_report(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DoctorCommand(), ['--strict' => true, '--only-problems' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('All checks passed', $tester->getDisplay());
    }

    /**
     * `config/*.php` matches the two environment files beside `config/app.php`,
     * and the base layer excludes them by name. The rule reads filenames and a
     * filename carries no intent, so every exclusion is named here: a project
     * whose `config/app-extra.php` is not an environment file at all finds out
     * from this check rather than from a value that stopped arriving (#889).
     *
     * A pass, not a warning -- this is what a correct project looks like, and
     * `test_doctor_has_nothing_to_report` above runs `--strict`.
     */
    public function test_doctor_names_the_config_files_excluded_from_the_base_layer(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DoctorCommand(), []);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('config environment layers', $display);

        // The separator between `config` and the filename is the platform's, so
        // the assertion starts at the filename.
        self::assertStringContainsString(
            'app-prod.php matches a base config path but is excluded from it: ',
            $display,
        );
        self::assertStringContainsString('read only when APP_ENV=prod' . PHP_EOL, $display);
        self::assertStringContainsString('read only when APP_ENV=prod and APP_REGION=eu', $display);
    }

    /**
     * The boundaries this application declared, checked against the graph it
     * actually has -- and the file is the same one both analysers read.
     */
    public function test_the_module_graph_breaks_none_of_the_declared_rules(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugGraphCommand(), [
            '--check' => true,
            '--rules' => ReferenceApp::rulesFile(),
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('No undeclared module dependency cycles', $tester->getDisplay());
        self::assertStringContainsString('No forbidden module dependencies', $tester->getDisplay());
    }

    public function test_the_mermaid_graph_names_all_five_modules(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugGraphCommand(), ['--format' => 'mermaid']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('graph TD', $display);

        foreach (['Billing', 'Customer', 'Notification', 'Payment', 'Reporting'] as $module) {
            self::assertStringContainsString('ReferenceApp.Invoicing.' . $module, $display);
        }
    }

    public function test_every_pillar_constructor_can_be_satisfied(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugModulesCommand(), ['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('20 pillars inspected, 0 unresolvable parameters', $tester->getDisplay());
    }

    public function test_the_declared_configuration_schema_is_satisfied(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new ValidateConfigCommand(), ['--strict' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Configuration is valid!', $tester->getDisplay());
    }

    /**
     * The two generated shapes are committed source, so this is the gate that
     * keeps them and the declaration in `gacela.php` from drifting apart.
     */
    public function test_the_generated_shapes_are_up_to_date(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DtoGenerateCommand(), ['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('2 class(es) already up to date', $tester->getDisplay());
    }

    /**
     * Nothing to migrate is the proof that every accessor carries
     * `#[ServiceMap]` rather than the `@method` docblock 3.0 removes.
     */
    public function test_no_accessor_is_left_on_the_docblock_fallback(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new MigrateServiceMapCommand(), ['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Nothing to migrate', $tester->getDisplay());
    }

    /**
     * Warming is only worth anything if the next process reads what it wrote,
     * and the resolver says so out loud: a cold run finds each class by
     * searching for it, a warm one finds it in the cache and never searches.
     */
    public function test_a_warmed_cache_is_read_by_the_next_bootstrap(): void
    {
        putenv('APP_ENV=prod');

        $cold = $this->eventsWhileResolvingACustomer();
        self::assertGreaterThan(0, $this->occurrencesOf($cold, ClassNameValidCandidateFoundEvent::class));
        self::assertSame(0, $this->occurrencesOf($cold, ClassNameCachedFoundEvent::class));

        Gacela::resetCache();
        ReferenceApp::bootstrap();

        $tester = $this->execute(new CacheWarmCommand(), []);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Cache warming complete!', $tester->getDisplay());
        self::assertStringContainsString('Classes resolved: 20', $tester->getDisplay());

        Gacela::resetCache();

        $warm = $this->eventsWhileResolvingACustomer();
        self::assertSame(0, $this->occurrencesOf($warm, ClassNameValidCandidateFoundEvent::class), 'the finder searched again');
        self::assertGreaterThan(0, $this->occurrencesOf($warm, ClassNameCachedFoundEvent::class));
    }

    public function test_clearing_the_cache_removes_what_warming_wrote(): void
    {
        putenv('APP_ENV=prod');
        ReferenceApp::bootstrap();

        $this->execute(new CacheWarmCommand(), []);
        self::assertNotSame([], $this->cacheFiles(), 'warming wrote nothing to clear');

        $tester = $this->execute(new CacheClearCommand(), []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Cache cleared successfully!', $tester->getDisplay());
        self::assertSame([], $this->cacheFiles());
    }

    public function test_list_modules_names_the_five_modules(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new ListModulesCommand(), []);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);

        foreach (['Billing', 'Customer', 'Notification', 'Payment', 'Reporting'] as $module) {
            self::assertStringContainsString('ReferenceApp\\Invoicing\\' . $module, $display);
        }
    }

    public function test_debug_provides_lists_the_declared_ids(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugProvidesCommand(), []);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('BILLING_CUSTOMER_FACADE', $display);
        self::assertStringContainsString('NOTIFICATION_DELIVERY_LOG', $display);
        self::assertStringContainsString('PAYMENT_PROCESSOR', $display);
    }

    public function test_debug_module_describes_the_billing_module(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugModuleCommand(), ['module' => 'Billing']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('Module: Billing', $display);
        self::assertStringContainsString('BillingFactory', $display);

        // What Billing lets Reporting read. The two analyser configs no longer
        // carry an entry for it, so this section is where a reader finds the
        // surface that used to be spelled out in phpstan-reference-app.neon.
        self::assertStringContainsString('Public API', $display);
        self::assertStringContainsString(InvoiceRecord::class, $display);
    }

    public function test_debug_container_reports_the_application_bindings(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugContainerCommand(), ['--stats' => true]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('Container Statistics', $display);
        self::assertStringContainsString('ClockInterface', $display);
    }

    public function test_debug_config_marks_every_key_as_declared(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugConfigCommand(), []);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('billing.currency', $display);
        self::assertStringNotContainsString('undeclared', $display);
    }

    public function test_debug_dependencies_resolves_the_billing_factory_constructor(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugDependenciesCommand(), [
            'class' => \GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFactory::class,
        ]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('Unresolvable: 0', $display);
    }

    public function test_debug_events_produces_its_catalogue(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugEventsCommand(), []);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('Gacela events', $display);
        self::assertStringContainsString('Summary:', $display);
    }

    /**
     * The one listener this application registers is registered in `gacela.php`,
     * and the command that answers "what is listening" used to answer "nothing"
     * about it: the merger put it on the dispatcher without recording it on the
     * setup both this command and `doctor` read (#887).
     *
     * Asserted through the output rather than the setup, because the output is
     * what somebody debugging a dead listener actually reads.
     */
    public function test_debug_events_reports_the_listener_registered_in_the_gacela_file(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DebugEventsCommand(), ['--listened' => true]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringNotContainsString('Nothing listens to any Gacela event.', $display);
        // One registration against the abstract parent, so all four resolver
        // events are covered by it and the target is named on each row.
        self::assertStringContainsString('1 listener via AbstractGacelaClassResolverEvent', $display);
        self::assertStringContainsString('4 with listeners', $display);
    }

    /**
     * The other half of #887: `doctor`'s event-listener check reported "no
     * specific listeners registered" for an application whose `gacela.php`
     * registers one -- and could therefore never judge its target either.
     */
    public function test_doctor_reports_the_listener_registered_in_the_gacela_file(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new DoctorCommand(), ['--json' => true]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('1 listener target(s) name a known event type', $display);
        self::assertStringNotContainsString('no specific listeners registered', $display);
    }

    /**
     * A dispatcher the application supplies is not in the listener table and
     * cannot be -- so the command says it is there rather than leaving a reader
     * to conclude from the table that these four listeners are everything.
     */
    public function test_debug_events_says_when_a_custom_dispatcher_is_installed(): void
    {
        ReferenceApp::bootstrap(static function (GacelaConfig $config): void {
            $config->setEventDispatcher(new RecordingEventDispatcher());
        });

        $tester = $this->execute(new DebugEventsCommand(), []);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString(
            'setEventDispatcher(RecordingEventDispatcher) is in effect, so every event above also reaches it.',
            $display,
        );
    }

    public function test_profile_report_produces_its_document(): void
    {
        ReferenceApp::bootstrap();

        Profiler::getInstance()->enable();
        Profiler::getInstance()->start('resolve', 'InvoicingToolingTest');
        Profiler::getInstance()->stop('resolve', 'InvoicingToolingTest');

        $tester = $this->execute(new ProfileReportCommand(), ['--format' => 'summary']);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('Total Operations: 1', $display);
    }

    /**
     * Asked what it would write, and told not to: the application directory
     * holds two generated files, both committed, and the editor metadata is
     * neither of them.
     */
    public function test_ide_meta_reports_what_it_would_write_without_writing_it(): void
    {
        ReferenceApp::bootstrap();

        $tester = $this->execute(new IdeMetaCommand(), ['--dry-run' => true]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('gacela.meta.php', $display);
        self::assertFileDoesNotExist(
            ReferenceApp::root() . DIRECTORY_SEPARATOR . '.phpstorm.meta.php' . DIRECTORY_SEPARATOR . 'gacela.meta.php',
        );
    }

    /**
     * The four commands that write a project rather than read one, run once
     * against a throwaway root -- which is how a project meets them.
     */
    public function test_a_project_can_be_scaffolded_stubbed_and_described(): void
    {
        $appRoot = $this->createTempDirectory('scaffold-root');
        $stubsDir = $this->createTempDirectory('scaffold-stubs');

        // `init` reads this to learn which namespace the generated `gacela.php`
        // should scan; `make:*` reads it to decide where the files go.
        file_put_contents($appRoot . '/composer.json', '{"autoload":{"psr-4":{"GacelaData\\\\":"data/"}}}');

        $init = $this->execute(new InitCommand($appRoot), []);
        self::assertSame(Command::SUCCESS, $init->getStatusCode(), $init->getDisplay());
        self::assertFileExists($appRoot . DIRECTORY_SEPARATOR . 'gacela.php');
        self::assertFileExists($appRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php');

        // No closure: the generated file is the configuration, which is how a
        // freshly scaffolded project is bootstrapped.
        Gacela::bootstrap($appRoot, static function (GacelaConfig $config) use ($stubsDir): void {
            $config->resetInMemoryCache();
            $config->setStubsDir($stubsDir);
        });

        $module = $this->execute(new MakeModuleCommand(), ['path' => 'GacelaData/' . self::SCAFFOLD_MODULE]);
        self::assertSame(Command::SUCCESS, $module->getStatusCode(), $module->getDisplay());
        self::assertStringContainsString(
            sprintf("Module '%s' created successfully", self::SCAFFOLD_MODULE),
            $module->getDisplay(),
        );

        $file = $this->execute(new MakeFileCommand(), [
            'path' => 'GacelaData/' . self::SCAFFOLD_FILE_MODULE,
            'filenames' => ['Facade', 'Factory'],
        ]);
        self::assertSame(Command::SUCCESS, $file->getStatusCode(), $file->getDisplay());
        self::assertStringContainsString('Facade.php', $file->getDisplay());

        $stubs = $this->execute(new StubsPublishCommand(), ['--template' => 'basic']);
        self::assertSame(Command::SUCCESS, $stubs->getStatusCode(), $stubs->getDisplay());
        self::assertFileExists($stubsDir . DIRECTORY_SEPARATOR . 'facade-maker.txt');

        $ide = $this->execute(new IdeMetaCommand(), []);
        self::assertSame(Command::SUCCESS, $ide->getStatusCode(), $ide->getDisplay());
        self::assertFileExists(
            $appRoot . DIRECTORY_SEPARATOR . '.phpstorm.meta.php' . DIRECTORY_SEPARATOR . 'gacela.meta.php',
        );
    }

    /**
     * The two analyser configurations still name the application. The runs
     * themselves are in the integration suite, and a config that stopped
     * pointing here would make both of them pass on nothing.
     */
    public function test_the_analyser_configurations_still_point_at_the_application(): void
    {
        $phpstan = (string)file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'phpstan-reference-app.neon');
        $psalm = (string)file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'psalm-reference-app.xml');

        self::assertStringContainsString('- Invoicing', $phpstan);
        self::assertStringContainsString('<directory name="Invoicing"/>', $psalm);
    }

    /**
     * @param array<string, bool|string|list<string>> $input
     */
    private function execute(Command $command, array $input): CommandTester
    {
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    /**
     * @return list<class-string>
     */
    private function eventsWhileResolvingACustomer(): array
    {
        $events = [];

        ReferenceApp::bootstrap(static function (GacelaConfig $config) use (&$events): void {
            $config->registerGenericListener(static function (GacelaEventInterface $event) use (&$events): void {
                $events[] = $event::class;
            });
        });

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');

        return $events;
    }

    /**
     * @param list<class-string> $events
     */
    private function occurrencesOf(array $events, string $eventClass): int
    {
        return count(array_filter($events, static fn (string $seen): bool => $seen === $eventClass));
    }

    /**
     * @return list<string>
     */
    private function cacheFiles(): array
    {
        return array_values(glob($this->cacheDir . DIRECTORY_SEPARATOR . 'gacela-*.php') ?: []);
    }

    private function createTempDirectory(string $purpose): string
    {
        $directory = ReferenceApp::createTempDirectory($purpose);
        $this->tempDirectories[] = $directory;

        return $directory;
    }

    /**
     * What `make:module` and `make:file` wrote, removed by the name this test
     * gave them and only from inside the repository's scratch directory.
     */
    private function removeScaffoldOutput(string $module): void
    {
        $scratch = (getcwd() ?: '.') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
        $directory = $scratch . $module;

        if (!is_dir($directory) || !str_starts_with($directory, $scratch)) {
            return;
        }

        DirectoryUtil::removeDir($directory);
    }
}
