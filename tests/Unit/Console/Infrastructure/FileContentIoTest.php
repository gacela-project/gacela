<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Infrastructure;

use Gacela\Console\Infrastructure\FileContentIo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function random_bytes;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

final class FileContentIoTest extends TestCase
{
    private string $workingDir = '';

    private FileContentIo $io;

    protected function setUp(): void
    {
        $this->workingDir = sys_get_temp_dir() . '/gacela-file-content-io-' . bin2hex(random_bytes(4));
        mkdir($this->workingDir, 0777, true);

        $this->io = new FileContentIo();
    }

    protected function tearDown(): void
    {
        foreach (['file.txt', 'nested'] as $entry) {
            $path = $this->workingDir . '/' . $entry;
            if (is_file($path)) {
                unlink($path);
            }

            if (is_dir($path)) {
                rmdir($path);
            }
        }

        if (is_dir($this->workingDir)) {
            rmdir($this->workingDir);
        }
    }

    public function test_it_creates_a_missing_directory(): void
    {
        $directory = $this->workingDir . '/nested';

        $this->io->mkdir($directory);

        self::assertDirectoryExists($directory);
    }

    public function test_it_leaves_an_existing_directory_alone(): void
    {
        // A second mkdir() on an existing directory would emit a warning, so
        // turning warnings into failures proves the guard short-circuits.
        set_error_handler(static function (int $errno, string $message): bool {
            throw new RuntimeException($message);
        });

        try {
            $this->io->mkdir($this->workingDir);
        } finally {
            restore_error_handler();
        }

        self::assertDirectoryExists($this->workingDir);
    }

    public function test_it_fails_when_the_directory_cannot_be_created(): void
    {
        $directory = $this->workingDir . '/missing-parent/nested';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Directory "%s" was not created', $directory));

        self::silenced(fn (): mixed => $this->io->mkdir($directory));
    }

    public function test_it_writes_the_file_contents(): void
    {
        $path = $this->workingDir . '/file.txt';

        $this->io->filePutContents($path, 'written');

        self::assertSame('written', file_get_contents($path));
    }

    public function test_it_fails_when_the_file_cannot_be_written(): void
    {
        $path = $this->workingDir . '/missing-parent/file.txt';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('File "%s" was not written', $path));

        self::silenced(fn (): mixed => $this->io->filePutContents($path, 'written'));
    }

    public function test_it_overwrites_an_existing_file(): void
    {
        $path = $this->workingDir . '/file.txt';
        file_put_contents($path, 'before');

        $this->io->filePutContents($path, 'after');

        self::assertSame('after', file_get_contents($path));
    }

    /**
     * Runs a call whose failure path is expected to emit a PHP warning, so the
     * assertion is about the exception and not about the warning.
     *
     * @param callable():mixed $call
     */
    private static function silenced(callable $call): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $call();
        } finally {
            restore_error_handler();
        }
    }
}
