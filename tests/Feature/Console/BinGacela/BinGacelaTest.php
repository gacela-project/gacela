<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\BinGacela;

use PHPUnit\Framework\TestCase;

use function dirname;
use function fclose;
use function mkdir;
use function proc_close;
use function proc_open;
use function rmdir;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;

use const PHP_BINARY;

final class BinGacelaTest extends TestCase
{
    public function test_it_exits_non_zero_and_writes_to_stderr_when_autoload_is_missing(): void
    {
        $binGacela = dirname(__DIR__, 4) . '/bin/gacela';
        $cwdWithoutVendor = sys_get_temp_dir() . '/gacela-bin-' . uniqid('', true);
        mkdir($cwdWithoutVendor);

        try {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open([PHP_BINARY, $binGacela], $descriptors, $pipes, $cwdWithoutVendor);
            self::assertIsResource($process);

            fclose($pipes[0]);
            $stdout = (string) stream_get_contents($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            self::assertSame(1, $exitCode, 'bin/gacela must exit 1 when it cannot load the autoloader');
            self::assertStringContainsString("Cannot load composer's autoload file", $stderr);
            self::assertStringNotContainsString(
                "Cannot load composer's autoload file",
                $stdout,
                'the error must be written to STDERR, not STDOUT',
            );
        } finally {
            rmdir($cwdWithoutVendor);
        }
    }
}
