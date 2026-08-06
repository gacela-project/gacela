<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor;

use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\Doctor\Fixtures\DegradedWithMetadataHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\DegradedWithoutMetadataHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\FakeHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\UnhealthyHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\UnhealthyWithMetadataHealthCheck;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function explode;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function rtrim;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

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

        $cacheFile = AbstractPhpFileCache::absoluteFilename($this->cacheDir, ClassNamePhpCache::FILENAME, __DIR__);
        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }

        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function test_reports_every_built_in_check_when_nothing_is_registered(): void
    {
        $tester = $this->doctor([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '✓ cache staleness',
            '✓ suffix configuration',
            '✓ pillar filenames',
            '✓ All checks passed',
        ], $this->statusLinesOf($tester));
    }

    public function test_a_registered_health_check_is_appended_to_the_built_in_ones(): void
    {
        $tester = $this->doctor([FakeHealthCheck::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // The registered check runs after the built-in ones, and its detail is shown.
        self::assertSame('✓ module health: FakeModule', $this->statusLinesOf($tester)[3]);
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
        $this->writeCacheEntry('some-cache-key', 'Never\\Declared\\Klass');

        $tester = $this->doctor([]);

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

    private function writeCacheEntry(string $key, string $className): void
    {
        file_put_contents(
            AbstractPhpFileCache::absoluteFilename($this->cacheDir, ClassNamePhpCache::FILENAME, __DIR__),
            sprintf("<?php\n\nreturn %s;\n", var_export([$key => $className], true)),
        );
    }

    /**
     * @param list<object|string> $healthChecks
     * @param array<string, bool|string> $input
     */
    private function doctor(array $healthChecks, array $input = []): CommandTester
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

        $tester = new CommandTester(new DoctorCommand());
        $tester->execute($input);

        return $tester;
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
