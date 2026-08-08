<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\BinGacela;

use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class BinGacelaTest extends TestCase
{
    public function test_it_exits_non_zero_and_writes_to_stderr_when_autoload_is_missing(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runBinGacela(autoloadStub: null);

        self::assertSame(1, $exitCode, 'bin/gacela must exit 1 when it cannot load the autoloader');
        self::assertStringContainsString("Cannot load composer's autoload file", $stderr);
        self::assertStringNotContainsString(
            "Cannot load composer's autoload file",
            $stdout,
            'the error must be written to STDERR, not STDOUT',
        );
    }

    public function test_it_exits_1_and_writes_to_stderr_when_symfony_console_is_missing(): void
    {
        // Autoloader that resolves nothing, so Symfony's Application class is absent.
        [$exitCode, $stdout, $stderr] = $this->runBinGacela(autoloadStub: '<?php');

        self::assertSame(1, $exitCode, 'bin/gacela must exit 1 when symfony/console is not installed');
        self::assertStringContainsString('gacela script failed', $stderr);
        self::assertStringNotContainsString('gacela script failed', $stdout);
    }

    public function test_it_exits_1_when_a_php_error_escapes_bootstrap(): void
    {
        // Symfony's Application exists (passes the class check) but Gacela's classes do not,
        // so Gacela::bootstrap() raises a PHP Error (not an Exception) that must still be caught.
        $stub = '<?php namespace Symfony\Component\Console; class Application {}';

        [$exitCode, $stdout, $stderr] = $this->runBinGacela(autoloadStub: $stub);

        self::assertSame(1, $exitCode, 'a PHP Error escaping bootstrap must still exit 1');
        self::assertStringContainsString('gacela script failed', $stderr);
        self::assertStringNotContainsString('gacela script failed', $stdout);
    }

    /**
     * The autoloader lives in the project root while the command is invoked two
     * directories below it. Composer tooling is expected to work anywhere in
     * the project, and this used to fail immediately looking for
     * `<subdirectory>/vendor/autoload.php`.
     */
    public function test_it_finds_the_project_root_when_invoked_from_a_subdirectory(): void
    {
        // The stub reports where it was loaded from, so the assertion can name
        // the directory rather than just observing that something worked.
        $stub = '<?php fwrite(STDOUT, "autoload-dir:" . __DIR__ . PHP_EOL);';

        [, $stdout, , $projectRoot] = $this->runBinGacela($stub, subdirectory: 'deep/nested');

        self::assertStringContainsString('autoload-dir:' . $projectRoot . '/vendor', $stdout);
    }

    public function test_it_still_loads_the_autoloader_when_invoked_from_the_project_root(): void
    {
        $stub = '<?php fwrite(STDOUT, "autoload-dir:" . __DIR__ . PHP_EOL);';

        [, $stdout, , $projectRoot] = $this->runBinGacela($stub);

        self::assertStringContainsString('autoload-dir:' . $projectRoot . '/vendor', $stdout);
    }

    /**
     * How the command is actually installed: composer symlinks
     * `vendor/bin/gacela` at the real script. The walk-up must start from the
     * working directory, not from wherever the script itself resolves to --
     * that path lives under `vendor/`, whose own parent is the project.
     */
    public function test_it_works_through_a_symlinked_vendor_bin_entry(): void
    {
        $projectRoot = sys_get_temp_dir() . '/gacela-bin-' . uniqid('', true);
        mkdir($projectRoot . '/vendor/bin', 0o777, true);
        file_put_contents(
            $projectRoot . '/vendor/autoload.php',
            '<?php fwrite(STDOUT, "autoload-dir:" . __DIR__ . PHP_EOL);',
        );

        $symlink = $projectRoot . '/vendor/bin/gacela';
        symlink(dirname(__DIR__, 4) . '/bin/gacela', $symlink);

        try {
            [, $stdout] = $this->runPhpScript($symlink, $projectRoot);

            self::assertStringContainsString(
                'autoload-dir:' . realpath($projectRoot) . '/vendor',
                $stdout,
            );
        } finally {
            unlink($symlink);
            unlink($projectRoot . '/vendor/autoload.php');
            rmdir($projectRoot . '/vendor/bin');
            rmdir($projectRoot . '/vendor');
            rmdir($projectRoot);
        }
    }

    /**
     * Runs bin/gacela in a throwaway working directory. When $autoloadStub is null the directory
     * has no vendor/autoload.php; otherwise the stub is written there as the autoloader.
     *
     * $subdirectory, when given, is created under the project root and used as the working
     * directory, leaving the autoloader above it.
     *
     * @return array{int, string, string, string} exit code, stdout, stderr, project root
     */
    private function runBinGacela(?string $autoloadStub, string $subdirectory = ''): array
    {
        $binGacela = dirname(__DIR__, 4) . '/bin/gacela';
        $projectRoot = sys_get_temp_dir() . '/gacela-bin-' . uniqid('', true);
        mkdir($projectRoot);

        $cwd = $projectRoot;
        if ($subdirectory !== '') {
            $cwd = $projectRoot . '/' . $subdirectory;
            mkdir($cwd, 0o777, true);
        }

        if ($autoloadStub !== null) {
            mkdir($projectRoot . '/vendor');
            file_put_contents($projectRoot . '/vendor/autoload.php', $autoloadStub);
        }

        try {
            [$exitCode, $stdout, $stderr] = $this->runPhpScript($binGacela, $cwd);

            // Resolved, because __DIR__ inside the stub reports the real path
            // and the system temp dir is a symlink on macOS.
            return [$exitCode, $stdout, $stderr, (string) realpath($projectRoot)];
        } finally {
            if ($autoloadStub !== null) {
                unlink($projectRoot . '/vendor/autoload.php');
                rmdir($projectRoot . '/vendor');
            }

            // Deepest first, so each directory is empty when it is removed.
            while ($cwd !== $projectRoot) {
                rmdir($cwd);
                $cwd = dirname($cwd);
            }

            rmdir($projectRoot);
        }
    }

    /**
     * @return array{int, string, string} exit code, stdout, stderr
     */
    private function runPhpScript(string $script, string $cwd): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([PHP_BINARY, $script], $descriptors, $pipes, $cwd);
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
