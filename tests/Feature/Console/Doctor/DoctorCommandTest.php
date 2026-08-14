<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor;

use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\Gacela;
use Gacela\Framework\Health\HealthStatus;
use Gacela\Framework\Health\ModuleHealthCheckInterface;
use GacelaTest\Feature\Console\Doctor\Fixtures\DegradedWithMetadataHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\DegradedWithoutMetadataHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\FakeHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\UnhealthyHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\UnhealthyWithMetadataHealthCheck;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_column;
use function array_filter;
use function array_unique;
use function array_values;
use function bin2hex;
use function count;
use function explode;
use function file_get_contents;
use function is_dir;
use function json_decode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function rtrim;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class DoctorCommandTest extends TestCase
{
    private string $cacheDir = '';

    protected function setUp(): void
    {
        // The default cache directory is `sys_get_temp_dir()`, which is shared
        // with every other Gacela app on the machine -- CacheStalenessCheck then
        // reports on someone else's entries and the exit code stops being a
        // function of this test. Point the cache somewhere this test owns.
        $this->cacheDir = sys_get_temp_dir() . '/gacela-doctor-' . bin2hex(random_bytes(4));
        mkdir($this->cacheDir, 0777, true);
        putenv('GACELA_CACHE_DIR=' . $this->cacheDir);
    }

    protected function tearDown(): void
    {
        putenv('GACELA_CACHE_DIR');

        // The dir is this test's own random-named temp dir, so sweeping it by
        // glob names exactly what the test created -- and needs no bootstrap,
        // which tearDown cannot assume survived the test.
        foreach (glob($this->cacheDir . '/gacela-*.php') ?: [] as $cacheFile) {
            unlink($cacheFile);
        }

        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    /**
     * `doctor` runs more checks than any other command and is the only one
     * whose exit code depends on a distinction the flags do not explain: an
     * error always fails, a warning fails only under `--strict`.
     *
     * The help is asserted for that model rather than for a list of checks --
     * a run already names every check it made, and a list here would go stale
     * the next time one is added.
     */
    public function test_the_help_explains_the_verdict_model(): void
    {
        $help = (new DoctorCommand())->getHelp();

        self::assertStringContainsString('always fail the run', $help);
        self::assertStringContainsString('--strict', $help);
        self::assertStringContainsString('narrows which modules get inspected, not which checks run', $help);
    }

    /**
     * The claim above about the filter, checked against the command rather than
     * trusted: every check reports either way, and only the modules they walk
     * change.
     */
    public function test_a_filter_matching_nothing_still_reports_every_check(): void
    {
        $unfiltered = $this->statusLinesOf($this->doctor([]));
        $filtered = $this->statusLinesOf($this->doctor([], ['filter' => 'NoSuchNamespaceXYZ']));

        // Two empty lists are also "the same", and would prove nothing.
        self::assertGreaterThan(10, count($unfiltered));
        self::assertSame($unfiltered, $filtered);
    }

    /**
     * `--format=xml` used to print the text report and exit 0, so a pipeline
     * that asked for a document it cannot produce could not tell that run apart
     * from a healthy one.
     */
    public function test_an_unknown_format_is_refused_rather_than_answered_with_text(): void
    {
        $tester = $this->doctor([], ['--format' => 'xml']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Unknown format "xml". Use one of: text, json', $tester->getDisplay());
        self::assertStringNotContainsString('Gacela Doctor', $tester->getDisplay());
    }

    public function test_reports_every_built_in_check_when_nothing_is_registered(): void
    {
        $tester = $this->doctor([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '✓ cache staleness',
            '✓ cache directory',
            '✓ suffix configuration',
            '✓ class filenames',
            '✓ undiscovered facades',
            '✓ unresolved pillar files',
            '✓ cacheable storage',
            '✓ plugin stacks',
            '✓ duplicate provided ids',
            '✓ unusable #[Provides]',
            '✓ config sources',
            '✓ config schema',
            '✓ published stubs',
            '✓ event listeners',
            '✓ service extensions',
            '✓ tagged services',
            '✓ package manifests',
            '✓ IDE metadata',
            '✓ All checks passed',
        ], $this->statusLinesOf($tester));
    }

    /**
     * An extension on an id no Provider ever set()s is accepted and applied
     * nowhere (#683); doctor is the surface that says so.
     */
    public function test_an_extension_on_an_id_nobody_registers_warns_naming_the_id(): void
    {
        $cacheDir = $this->cacheDir;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false, $cacheDir);
            $config->extendService('ARRAY_AS_OBJETC', static function (): void {
            });
        });

        $tester = new CommandTester(new DoctorCommand());
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), 'a warning must not fail a non-strict run');
        self::assertContains('⚠ service extensions', $this->statusLinesOf($tester));
        self::assertStringContainsString('ARRAY_AS_OBJETC', $tester->getDisplay());
    }

    public function test_an_unmatched_extension_fails_a_strict_run(): void
    {
        $cacheDir = $this->cacheDir;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false, $cacheDir);
            $config->extendService('ARRAY_AS_OBJETC', static function (): void {
            });
        });

        $tester = new CommandTester(new DoctorCommand());
        $tester->execute(['--strict' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    /**
     * A full run is a lot of "✓" to read to find the one "⚠". `-q` is not the
     * answer: it suppresses everything, so `--strict -q` fails a build with no
     * indication of what failed.
     *
     * The count is deliberately not written out here. The changelog's copy of
     * this sentence is guarded by
     * `test_the_changelog_counts_the_checks_doctor_actually_runs()`; an
     * unguarded second copy would just be one more number to get wrong -- as
     * this one was, sitting at "Twelve" through six more checks.
     */
    public function test_only_problems_hides_the_passing_checks(): void
    {
        $tester = $this->doctor([UnhealthyHealthCheck::class], ['--only-problems' => true]);

        $lines = $this->statusLinesOf($tester);

        self::assertContains('✗ module health: UnhealthyModule', $lines);
        self::assertNotContains('✓ cache staleness', $lines);
        self::assertNotContains('✓ event listeners', $lines);
    }

    /**
     * The remediation is the reason to read the line at all, so hiding the
     * passing checks must not hide it.
     */
    public function test_only_problems_keeps_the_detail_of_what_it_reports(): void
    {
        $tester = $this->doctor([DegradedWithMetadataHealthCheck::class], ['--only-problems' => true]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Cache is stale', $display);
        self::assertStringContainsString('stale-entries: 42', $display);
    }

    /**
     * A clean run still says so. Printing nothing would leave "did it even
     * run?" open -- which is what `-q` already does.
     */
    public function test_only_problems_still_reports_a_clean_run(): void
    {
        $tester = $this->doctor([], ['--only-problems' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('✓ All checks passed', $this->statusLinesOf($tester));
    }

    public function test_only_problems_does_not_change_the_exit_code(): void
    {
        $strict = $this->doctor([DegradedWithoutMetadataHealthCheck::class], ['--only-problems' => true, '--strict' => true]);

        self::assertSame(Command::FAILURE, $strict->getStatusCode(), 'a warning still fails --strict');
    }

    public function test_a_registered_health_check_is_appended_to_the_built_in_ones(): void
    {
        $tester = $this->doctor([FakeHealthCheck::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // The registered check runs after the built-in ones, and its detail is
        // shown. Located from the end rather than by index: which position that
        // is changes whenever a built-in check is added, and the claim here is
        // about the order, not the count.
        $lines = $this->statusLinesOf($tester);

        self::assertSame('✓ All checks passed', array_pop($lines));
        self::assertSame('✓ module health: FakeModule', array_pop($lines));
        self::assertStringContainsString('FakeHealthCheck ran', $tester->getDisplay());
    }

    public function test_a_health_check_instance_is_accepted_too(): void
    {
        $tester = $this->doctor([new FakeHealthCheck()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('✓ module health: FakeModule', $this->linesOf($tester));
    }

    public function test_a_degraded_check_is_a_warning_and_lists_its_metadata(): void
    {
        $tester = $this->doctor([DegradedWithMetadataHealthCheck::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), 'degraded is a warning, not a failure');
        $display = $tester->getDisplay();

        // A degraded check warns rather than fails, and lists its metadata --
        // including a non-scalar value, rendered as its type.
        self::assertContains('⚠ module health: DegradedModule', $this->statusLinesOf($tester));
        self::assertStringContainsString('Cache is stale', $display);
        self::assertStringContainsString('stale-entries: 42', $display);
        self::assertStringContainsString('oldest-entry: 2020-01-01', $display);
        self::assertStringContainsString('raw-payload: array', $display);
        self::assertContains('⚠ Doctor finished with warnings', $this->statusLinesOf($tester));
    }

    public function test_an_unhealthy_check_is_an_error_and_lists_its_metadata(): void
    {
        $tester = $this->doctor([UnhealthyWithMetadataHealthCheck::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $tester->getDisplay();

        // An unhealthy check is an error, and its metadata is listed too.
        self::assertContains('✗ module health: UnhealthyMetaModule', $this->statusLinesOf($tester));
        self::assertStringContainsString('Broker unreachable', $display);
        self::assertStringContainsString('host: broker.internal', $display);
        self::assertStringContainsString('attempts: 3', $display);
        self::assertContains('✗ Doctor found errors', $this->statusLinesOf($tester));
    }

    public function test_a_degraded_check_without_metadata_only_shows_its_detail(): void
    {
        $tester = $this->doctor([DegradedWithoutMetadataHealthCheck::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // With no metadata the check shows its detail and nothing more.
        self::assertContains('⚠ module health: DegradedBareModule', $this->statusLinesOf($tester));
        self::assertStringContainsString('Slow response times', $tester->getDisplay());
    }

    public function test_an_error_after_a_warning_still_fails(): void
    {
        $tester = $this->doctor([
            DegradedWithoutMetadataHealthCheck::class,
            UnhealthyHealthCheck::class,
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('✗ Doctor found errors', $this->linesOf($tester));
    }

    public function test_a_warning_after_an_error_still_fails(): void
    {
        $tester = $this->doctor([
            UnhealthyHealthCheck::class,
            DegradedWithoutMetadataHealthCheck::class,
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('✗ Doctor found errors', $this->linesOf($tester));
    }

    public function test_a_passing_check_after_an_error_still_fails(): void
    {
        $tester = $this->doctor([
            UnhealthyHealthCheck::class,
            FakeHealthCheck::class,
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('✗ Doctor found errors', $this->linesOf($tester));
    }

    public function test_checks_for_the_same_module_are_combined_without_hiding_an_error(): void
    {
        $unhealthy = $this->createStub(ModuleHealthCheckInterface::class);
        $unhealthy->method('getModuleName')->willReturn('Orders');
        $unhealthy->method('checkHealth')->willReturn(HealthStatus::unhealthy('Database down'));

        $healthy = $this->createStub(ModuleHealthCheckInterface::class);
        $healthy->method('getModuleName')->willReturn('Orders');
        $healthy->method('checkHealth')->willReturn(HealthStatus::healthy('Queue up'));

        $tester = $this->doctor([$unhealthy, $healthy]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame(1, substr_count($tester->getDisplay(), 'module health: Orders'));
        self::assertStringContainsString('[unhealthy] Database down', $tester->getDisplay());
        self::assertStringContainsString('[healthy] Queue up', $tester->getDisplay());
    }

    public function test_a_passing_check_after_a_warning_stays_a_warning(): void
    {
        $tester = $this->doctor([
            DegradedWithoutMetadataHealthCheck::class,
            FakeHealthCheck::class,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('⚠ Doctor finished with warnings', $this->linesOf($tester));
    }

    public function test_strict_turns_a_warning_into_a_failure(): void
    {
        $tester = $this->doctor([DegradedWithoutMetadataHealthCheck::class], ['--strict' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('⚠ Doctor finished with warnings', $this->linesOf($tester));
    }

    public function test_strict_still_succeeds_when_everything_passes(): void
    {
        $tester = $this->doctor([FakeHealthCheck::class], ['--strict' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_a_stale_cache_entry_is_reported_with_its_remediation(): void
    {
        // Bootstrap before writing: the entry's filename carries the current
        // bootstrap's fingerprint (#686), which does not exist before one --
        // and under an unlucky seed this test runs first (main went red on
        // exactly that).
        $this->bootstrapDoctor([]);
        $this->writeCacheEntry('some-cache-key', 'Never\\Declared\\Klass');

        $tester = new CommandTester(new DoctorCommand());
        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // A cache entry pointing at a class that no longer exists is a warning,
        // and the check tells the user how to fix it.
        self::assertContains('⚠ cache staleness', $this->statusLinesOf($tester));
        self::assertStringContainsString(
            'missing source: some-cache-key → Never\\Declared\\Klass (source file not found)',
            $display,
        );
        self::assertStringContainsString(
            'run `bin/gacela cache:clear && bin/gacela cache:warm` to rebuild',
            $display,
        );
    }

    public function test_the_filter_argument_narrows_the_module_scoped_checks(): void
    {
        $tester = $this->doctor([], ['filter' => 'DoesNotExist']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('    no modules discovered', $this->linesOf($tester));
    }

    /**
     * `--strict` already answers "did anything go wrong" with an exit code. A
     * job that wants to say *which* check, and repeat its remediation into a
     * review comment, had to parse the prose -- which is why
     * `debug:graph --check` grew `--format=json` first.
     */
    public function test_json_reports_every_check_with_its_status(): void
    {
        $display = $this->doctor([], ['--format' => 'json'])->getDisplay();

        /** @var array{status: string, checks: list<array{name: string, status: string}>} $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $decoded['status']);
        self::assertContains('cache staleness', array_column($decoded['checks'], 'name'));
        self::assertSame(['ok'], array_unique(array_column($decoded['checks'], 'status')));
    }

    /**
     * The status vocabulary is the enum's own values, so a consumer matching on
     * `error` is matching what `CheckStatus` already carries rather than a
     * second spelling invented for the report.
     */
    public function test_json_carries_the_failing_check_and_its_remediation(): void
    {
        $display = $this->doctor([UnhealthyHealthCheck::class], ['--format' => 'json'])->getDisplay();

        /** @var array{status: string, checks: list<array{name: string, status: string, details: list<string>, remediation: string}>} $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('error', $decoded['status']);

        $failing = array_values(array_filter($decoded['checks'], static fn (array $c): bool => $c['status'] === 'error'));
        self::assertNotSame([], $failing);
        self::assertNotSame([], $failing[0]['details']);
    }

    /**
     * The flag means the same thing in both formats, or a job that adds
     * `--format=json` to an existing `--only-problems` invocation quietly starts
     * reporting everything.
     */
    public function test_only_problems_narrows_the_json_too(): void
    {
        $display = $this->doctor([], ['--format' => 'json', '--only-problems' => true])->getDisplay();

        /** @var array{status: string, checks: list<mixed>} $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ok', $decoded['status'], 'a clean run still reports its overall status');
        self::assertSame([], $decoded['checks']);
    }

    public function test_json_keeps_the_exit_code_the_text_format_gives(): void
    {
        self::assertSame(
            Command::FAILURE,
            $this->doctor([UnhealthyHealthCheck::class], ['--format' => 'json'])->getStatusCode(),
        );

        self::assertSame(
            Command::FAILURE,
            $this->doctor([DegradedWithoutMetadataHealthCheck::class], ['--format' => 'json', '--strict' => true])->getStatusCode(),
            'a warning still fails --strict',
        );

        self::assertSame(
            Command::SUCCESS,
            $this->doctor([DegradedWithoutMetadataHealthCheck::class], ['--format' => 'json'])->getStatusCode(),
            'and still passes without it',
        );
    }

    /**
     * Two spellings grew side by side -- `--json` where a command has exactly
     * two formats, `--format` where it has more -- and a reader who learned one
     * met "The --json option does not exist." on the other. Both work now.
     */
    public function test_json_shorthand_gives_the_same_document_as_the_format_option(): void
    {
        $shorthand = $this->doctor([], ['--json' => true])->getDisplay();
        $explicit = $this->doctor([], ['--format' => 'json'])->getDisplay();

        self::assertJson($shorthand);
        self::assertSame(
            json_decode($explicit, true, 512, JSON_THROW_ON_ERROR),
            json_decode($shorthand, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The `--only-problems` changelog entry opens by counting the checks in
     * words -- "<N> checks is a lot of ✓ to read to find the one ⚠" -- and
     * adding a check makes that wrong. It had been wrong twice before this
     * guard: #796 took it to thirteen and #797 to fourteen, both caught by
     * hand, both while still in `## Unreleased` and shipping.
     *
     * #798 guarded the sibling count in `docs/cli.md` and deliberately left
     * this one alone, because it needed the number of checks the command
     * actually registers and coupling a text assertion to the command looked
     * fragile. `doctor --json` removed that objection: the count is a field in
     * a document now.
     */
    public function test_the_changelog_counts_the_checks_doctor_actually_runs(): void
    {
        /** @var array{checks: list<mixed>} $report */
        $report = json_decode($this->doctor([], ['--json' => true])->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        $actual = count($report['checks']);

        $words = [
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
            19 => 'Nineteen', 20 => 'Twenty',
        ];
        self::assertArrayHasKey($actual, $words, 'the doctor grew past the words this test knows');

        $changelog = (string) file_get_contents(__DIR__ . '/../../../../CHANGELOG.md');

        self::assertMatchesRegularExpression(
            '/^- Print only the checks that found something with `doctor --only-problems`\. ' . $words[$actual] . ' checks/m',
            $changelog,
            sprintf('doctor runs %d checks; the --only-problems changelog entry says otherwise', $actual),
        );
    }

    private function writeCacheEntry(string $key, string $className): void
    {
        file_put_contents(
            AbstractPhpFileCache::absoluteFilename(
                $this->cacheDir,
                ClassNamePhpCache::FILENAME,
                __DIR__,
                ClassResolverCache::bootstrapFingerprint(),
            ),
            sprintf("<?php\n\nreturn %s;\n", var_export([$key => $className], true)),
        );
    }

    /**
     * @param list<object|string> $healthChecks
     * @param array<string, bool|string> $input
     */
    private function doctor(array $healthChecks, array $input = []): CommandTester
    {
        $this->bootstrapDoctor($healthChecks);

        $tester = new CommandTester(new DoctorCommand());
        $tester->execute($input);

        return $tester;
    }

    /**
     * @param list<object|string> $healthChecks
     */
    private function bootstrapDoctor(array $healthChecks): void
    {
        $cacheDir = $this->cacheDir;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($healthChecks, $cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false, $cacheDir);
            foreach ($healthChecks as $healthCheck) {
                /** @psalm-suppress ArgumentTypeCoercion */
                $config->addHealthCheck($healthCheck);
            }
        });
    }

    /**
     * The status lines only: which checks ran, how each came out, and the verdict.
     * Keeps the assertions off the separators, blank lines and indentation around them.
     *
     * @return list<string>
     */
    private function statusLinesOf(CommandTester $tester): array
    {
        return array_values(array_filter(
            $this->linesOf($tester),
            static fn (string $line): bool => preg_match('/^[✓⚠✗] /u', $line) === 1,
        ));
    }

    /**
     * @return list<string>
     */
    private function linesOf(CommandTester $tester): array
    {
        return array_map(
            rtrim(...),
            explode("\n", $tester->getDisplay()),
        );
    }
}
