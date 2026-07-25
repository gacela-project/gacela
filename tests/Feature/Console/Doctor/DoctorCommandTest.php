<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor;

use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
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

use function array_slice;
use function bin2hex;
use function explode;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function rtrim;
use function sprintf;
use function str_repeat;
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

        $cacheFile = $this->cacheDir . '/' . ClassNamePhpCache::FILENAME;
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
            '',
            'Gacela Doctor',
            self::separator(),
            '',
            '✓ cache staleness',
            '    all cache entries are fresh',
            '',
            '✓ suffix configuration',
            '    no modules discovered',
            '',
            '✓ pillar filenames',
            '    every pillar class matches its filename',
            '',
            '',
            self::separator(),
            '✓ All checks passed',
            '',
            '',
        ], self::linesOf($tester));
    }

    public function test_a_registered_health_check_is_appended_to_the_built_in_ones(): void
    {
        $tester = $this->doctor([FakeHealthCheck::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '✓ module health: FakeModule',
            '    FakeHealthCheck ran',
            '',
            '',
            self::separator(),
            '✓ All checks passed',
            '',
            '',
        ], array_slice(self::linesOf($tester), 13));
    }

    public function test_a_health_check_instance_is_accepted_too(): void
    {
        $tester = $this->doctor([new FakeHealthCheck()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('✓ module health: FakeModule', self::linesOf($tester));
    }

    public function test_a_degraded_check_is_a_warning_and_lists_its_metadata(): void
    {
        $tester = $this->doctor([DegradedWithMetadataHealthCheck::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), 'degraded is a warning, not a failure');
        self::assertSame([
            '⚠ module health: DegradedModule',
            '    Cache is stale',
            '    stale-entries: 42',
            '    oldest-entry: 2020-01-01',
            '    raw-payload: array',
            '',
            '',
            self::separator(),
            '⚠ Doctor finished with warnings',
            '',
            '',
        ], array_slice(self::linesOf($tester), 13));
    }

    public function test_an_unhealthy_check_is_an_error_and_lists_its_metadata(): void
    {
        $tester = $this->doctor([UnhealthyWithMetadataHealthCheck::class]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame([
            '✗ module health: UnhealthyMetaModule',
            '    Broker unreachable',
            '    host: broker.internal',
            '    attempts: 3',
            '',
            '',
            self::separator(),
            '✗ Doctor found errors',
            '',
            '',
        ], array_slice(self::linesOf($tester), 13));
    }

    public function test_a_degraded_check_without_metadata_only_shows_its_detail(): void
    {
        $tester = $this->doctor([DegradedWithoutMetadataHealthCheck::class]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '⚠ module health: DegradedBareModule',
            '    Slow response times',
            '',
        ], array_slice(self::linesOf($tester), 13, 3));
    }

    public function test_an_error_after_a_warning_still_fails(): void
    {
        $tester = $this->doctor([
            DegradedWithoutMetadataHealthCheck::class,
            UnhealthyHealthCheck::class,
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('✗ Doctor found errors', self::linesOf($tester));
    }

    public function test_a_warning_after_an_error_still_fails(): void
    {
        $tester = $this->doctor([
            UnhealthyHealthCheck::class,
            DegradedWithoutMetadataHealthCheck::class,
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('✗ Doctor found errors', self::linesOf($tester));
    }

    public function test_a_passing_check_after_an_error_still_fails(): void
    {
        $tester = $this->doctor([
            UnhealthyHealthCheck::class,
            FakeHealthCheck::class,
        ]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('✗ Doctor found errors', self::linesOf($tester));
    }

    public function test_a_passing_check_after_a_warning_stays_a_warning(): void
    {
        $tester = $this->doctor([
            DegradedWithoutMetadataHealthCheck::class,
            FakeHealthCheck::class,
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('⚠ Doctor finished with warnings', self::linesOf($tester));
    }

    public function test_strict_turns_a_warning_into_a_failure(): void
    {
        $tester = $this->doctor([DegradedWithoutMetadataHealthCheck::class], ['--strict' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains('⚠ Doctor finished with warnings', self::linesOf($tester));
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

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '',
            'Gacela Doctor',
            self::separator(),
            '',
            '⚠ cache staleness',
            '    missing source: some-cache-key → Never\\Declared\\Klass (source file not found)',
            '    → run `bin/gacela cache:clear && bin/gacela cache:warm` to rebuild',
            '',
        ], array_slice(self::linesOf($tester), 0, 8));
    }

    public function test_the_filter_argument_narrows_the_module_scoped_checks(): void
    {
        $tester = $this->doctor([], ['filter' => 'DoesNotExist']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('    no modules discovered', self::linesOf($tester));
    }

    private function writeCacheEntry(string $key, string $className): void
    {
        file_put_contents(
            $this->cacheDir . '/' . ClassNamePhpCache::FILENAME,
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
