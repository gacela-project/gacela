<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\CacheWarm;

use Gacela\Console\Application\CacheWarm\CacheWarmOutputFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function implode;
use function str_repeat;

use const PHP_EOL;

/**
 * Every method here is a pure "what does cache:warm print" contract, so each
 * one is asserted against its full, exact output: a dropped line or a blank
 * line that stops being written is a visible regression in the command.
 */
final class CacheWarmOutputFormatterTest extends TestCase
{
    private const SEPARATOR_WIDTH = 60;

    private BufferedOutput $output;

    private CacheWarmOutputFormatter $formatter;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
        $this->formatter = new CacheWarmOutputFormatter($this->output);
    }

    public function test_header_frames_the_banner_with_blank_lines(): void
    {
        $this->formatter->writeHeader();

        self::assertSame(
            self::lines('', 'Warming Gacela cache...', str_repeat('=', self::SEPARATOR_WIDTH), ''),
            $this->output->fetch(),
        );
    }

    public function test_cache_cleared_is_followed_by_a_blank_line(): void
    {
        $this->formatter->writeCacheCleared();

        self::assertSame(self::lines('Cleared existing cache', ''), $this->output->fetch());
    }

    public function test_module_discovery_warning_surfaces_the_underlying_error(): void
    {
        $this->formatter->writeModuleDiscoveryWarning('composer.json is not readable');

        self::assertSame(
            self::lines(
                'Warning: Some modules could not be discovered due to errors',
                '  Error: composer.json is not readable',
            ),
            $this->output->fetch(),
        );
    }

    public function test_modules_found_reports_the_count(): void
    {
        $this->formatter->writeModulesFound(['a', 'b', 'c']);

        self::assertSame(self::lines('Found 3 modules', ''), $this->output->fetch());
    }

    public function test_module_name_is_prefixed_with_processing(): void
    {
        $this->formatter->writeModuleName('Foo');

        self::assertSame(self::lines('Processing: Foo'), $this->output->fetch());
    }

    public function test_resolved_class_is_reported_with_its_type(): void
    {
        $this->formatter->writeClassResolved('Factory', 'App\\Foo\\FooFactory');

        self::assertSame(self::lines('  ✓ Resolved Factory: App\\Foo\\FooFactory'), $this->output->fetch());
    }

    public function test_skipped_class_explains_why_it_was_skipped(): void
    {
        $this->formatter->writeClassSkipped('Config', 'App\\Foo\\FooConfig');

        self::assertSame(
            self::lines('  ⚠ Skipped Config: App\\Foo\\FooConfig (class not found)'),
            $this->output->fetch(),
        );
    }

    public function test_failed_class_includes_the_error_message(): void
    {
        $this->formatter->writeClassFailed('Provider', 'App\\Foo\\FooProvider', 'cannot construct');

        self::assertSame(
            self::lines('  ✗ Failed Provider: App\\Foo\\FooProvider (cannot construct)'),
            $this->output->fetch(),
        );
    }

    public function test_empty_line_writes_exactly_one_newline(): void
    {
        $this->formatter->writeEmptyLine();

        self::assertSame(PHP_EOL, $this->output->fetch());
    }

    public function test_summary_lists_every_counter(): void
    {
        $this->formatter->writeSummary(3, 7, 2, 1, '0.123 seconds', '1.00 KB');

        self::assertSame(
            self::lines(
                str_repeat('=', self::SEPARATOR_WIDTH),
                'Cache warming complete!',
                '',
                'Modules processed: 3',
                'Classes resolved: 7',
                'Classes skipped: 2',
                'Classes failed: 1',
                'Time taken: 0.123 seconds',
                'Memory used: 1.00 KB',
                '',
            ),
            $this->output->fetch(),
        );
    }

    public function test_cache_info_shows_the_file_and_its_size(): void
    {
        $this->formatter->writeCacheInfo('/tmp/gacela/class-name-cache.php', '1.00 KB');

        self::assertSame(
            self::lines(
                'Cache file: /tmp/gacela/class-name-cache.php',
                'Cache size: 1.00 KB',
                '',
            ),
            $this->output->fetch(),
        );
    }

    public function test_merged_config_cache_info_shows_the_file_and_its_size(): void
    {
        $this->formatter->writeMergedConfigCacheInfo('/tmp/gacela/merged-config.php', '2.00 KB');

        self::assertSame(
            self::lines(
                'Merged config cache: /tmp/gacela/merged-config.php',
                'Merged config size: 2.00 KB',
                '',
            ),
            $this->output->fetch(),
        );
    }

    public function test_cache_warning_explains_how_to_enable_file_caching(): void
    {
        $this->formatter->writeCacheWarning();

        self::assertSame(
            self::lines(
                'Warning: Cache file was not created. File caching might be disabled.',
                'Enable file caching in your gacela.php configuration:',
                '  $config->enableFileCache();',
                '',
            ),
            $this->output->fetch(),
        );
    }

    private static function lines(string ...$lines): string
    {
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
