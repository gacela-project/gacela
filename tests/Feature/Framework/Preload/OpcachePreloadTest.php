<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Preload;

use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function extension_loaded;
use function file_get_contents;
use function is_file;
use function random_bytes;
use function shell_exec;
use function sprintf;
use function sys_get_temp_dir;

/**
 * Runs the shipped preload script the way php-fpm does, in its own process.
 *
 * Nothing short of this catches the failure it guards against: a class whose
 * parent, interface or trait was not preloaded compiles fine, is reported as
 * loaded, and is then dropped from the image with a warning only PHP itself
 * emits -- at startup, into a log, in production.
 */
final class OpcachePreloadTest extends TestCase
{
    private string $errorLog = '';

    protected function setUp(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('opcache.preload is not supported on Windows');
        }

        if (!extension_loaded('Zend OPcache')) {
            self::markTestSkipped('opcache is not available');
        }

        $this->errorLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-preload-' . bin2hex(random_bytes(4)) . '.log';
    }

    protected function tearDown(): void
    {
        self::assertNotSame('', $this->errorLog);
        self::assertStringStartsWith(sys_get_temp_dir() . DIRECTORY_SEPARATOR, $this->errorLog);

        if (is_file($this->errorLog)) {
            unlink($this->errorLog);
        }
    }

    public function test_the_whole_framework_preloads_and_links(): void
    {
        $output = $this->runWithPreload('echo "REQUEST OK";');

        self::assertStringContainsString('REQUEST OK', $output, 'the request after preloading did not run');
        self::assertStringNotContainsString("Can't preload unlinked class", $output);
        self::assertStringContainsString('0 skipped', $output);
    }

    /**
     * Preloading is only worth anything if the application still boots on top
     * of it -- the image is shared, so a class linked wrongly breaks every
     * request, not one.
     */
    public function test_gacela_bootstraps_on_top_of_the_preloaded_image(): void
    {
        $root = $this->projectRoot();

        $output = $this->runWithPreload(sprintf(
            'require %s; ' . \Gacela\Framework\Gacela::class . '::bootstrap(%s); echo "BOOTSTRAP OK";',
            escapeshellarg($root . '/vendor/autoload.php'),
            escapeshellarg($root),
        ));

        self::assertStringContainsString('BOOTSTRAP OK', $output);
        self::assertStringNotContainsString("Can't preload unlinked class", $output);
    }

    private function runWithPreload(string $code): string
    {
        $command = sprintf(
            '%s -d opcache.enable_cli=1 -d opcache.preload=%s -d log_errors=1 -d error_log=%s -r %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->projectRoot() . '/resources/gacela-preload.php'),
            escapeshellarg($this->errorLog),
            escapeshellarg($code),
        );

        $stdout = (string)shell_exec($command);
        $logged = is_file($this->errorLog) ? (string)file_get_contents($this->errorLog) : '';

        return $stdout . "\n" . $logged;
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }
}
