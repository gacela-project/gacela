<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Infrastructure;

use Gacela\Console\Infrastructure\FileContentIo;
use GacelaTest\Fixtures\FailingWriteStreamWrapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function random_bytes;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function sys_get_temp_dir;

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
        self::removeRecursively($this->workingDir);
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

    public function test_mkdir_reports_an_existing_file_without_a_warning(): void
    {
        $file = $this->workingDir . DIRECTORY_SEPARATOR . 'existing-file';
        file_put_contents($file, 'unchanged');
        set_error_handler(static function (int $errno, string $message): never {
            throw new RuntimeException($message);
        });

        try {
            $this->io->mkdir($file);
            self::fail('A file cannot be used as a directory.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(sprintf('"%s" is a file, not a directory', $file), $runtimeException->getMessage());
            self::assertSame('unchanged', file_get_contents($file));
        } finally {
            restore_error_handler();
        }
    }

    public function test_it_creates_missing_parent_directories(): void
    {
        // The first module of a new project: `src/` does not exist yet, so a
        // non-recursive mkdir() left `init` then `make:module` creating nothing.
        $directory = $this->workingDir . '/src/Hello';

        $this->io->mkdir($directory);

        self::assertDirectoryExists($directory);
    }

    public function test_it_fails_when_the_directory_cannot_be_created(): void
    {
        // A file where a parent directory should be: mkdir() cannot recurse
        // through it, and unlike chmod this behaves the same on Windows.
        $blocker = $this->workingDir . '/blocker';
        file_put_contents($blocker, 'not a directory');

        $directory = $blocker . '/nested';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Directory "%s" was not created', $directory));

        $this->silenced(fn (): mixed => $this->io->mkdir($directory));
    }

    public function test_it_writes_the_file_contents(): void
    {
        $path = $this->workingDir . '/file.txt';

        $this->io->filePutContents($path, 'written');

        self::assertSame('written', file_get_contents($path));
        self::assertSame([], glob($this->workingDir . DIRECTORY_SEPARATOR . '*.tmp'));
    }

    public function test_it_fails_when_the_file_cannot_be_written(): void
    {
        $path = $this->workingDir . '/missing-parent/file.txt';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('File "%s" was not written', $path));

        $this->silenced(fn (): mixed => $this->io->filePutContents($path, 'written'));
    }

    public function test_it_overwrites_an_existing_file(): void
    {
        $path = $this->workingDir . '/file.txt';
        file_put_contents($path, 'before');

        $this->io->filePutContents($path, 'after');

        self::assertSame('after', file_get_contents($path));
    }

    public function test_it_replaces_a_read_only_file_without_opening_it_for_writing(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Read-only file replacement differs on Windows.');
        }

        $path = $this->workingDir . DIRECTORY_SEPARATOR . 'file.txt';
        file_put_contents($path, 'before');
        chmod($path, 0444);

        $this->io->filePutContents($path, 'after');

        self::assertSame('after', file_get_contents($path));
        self::assertSame([], glob($this->workingDir . DIRECTORY_SEPARATOR . '*.tmp'));
    }

    public function test_a_failed_staging_write_keeps_the_existing_file(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Directory write permissions differ on Windows.');
        }

        $path = $this->workingDir . DIRECTORY_SEPARATOR . 'file.txt';
        file_put_contents($path, 'before');
        chmod($this->workingDir, 0555);

        try {
            $this->silenced(fn (): mixed => $this->io->filePutContents($path, 'after'));
            self::fail('Writing a staged file in a read-only directory must fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('left as it was', $runtimeException->getMessage());
            self::assertSame('before', file_get_contents($path));
            self::assertSame([], glob($this->workingDir . DIRECTORY_SEPARATOR . '*.tmp'));
        } finally {
            chmod($this->workingDir, 0755);
        }
    }

    public function test_a_short_write_is_not_promoted_to_the_target(): void
    {
        FailingWriteStreamWrapper::register();
        FailingWriteStreamWrapper::$bytesWritable = 2;
        try {
            $this->expectException(RuntimeException::class);
            $this->silenced(fn (): mixed => $this->io->filePutContents(FailingWriteStreamWrapper::DIRECTORY . DIRECTORY_SEPARATOR . 'file.txt', 'after'));
        } finally {
            FailingWriteStreamWrapper::unregister();
            self::assertFalse(FailingWriteStreamWrapper::$renamed);
        }
    }

    public function test_a_failed_rename_leaves_no_staging_file(): void
    {
        $path = $this->workingDir . DIRECTORY_SEPARATOR . 'existing-directory';
        mkdir($path);
        file_put_contents($path . DIRECTORY_SEPARATOR . 'original.txt', 'before');

        try {
            $this->silenced(fn (): mixed => $this->io->filePutContents($path, 'after'));
            self::fail('Replacing a directory with a file must fail.');
        } catch (RuntimeException) {
            self::assertSame('before', file_get_contents($path . DIRECTORY_SEPARATOR . 'original.txt'));
            self::assertSame([], glob($this->workingDir . DIRECTORY_SEPARATOR . '*.tmp'));
        }
    }

    public function test_replacement_preserves_existing_permission_bits(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Executable permission bits are not supported on Windows.');
        }

        $path = $this->workingDir . DIRECTORY_SEPARATOR . 'script.php';
        file_put_contents($path, 'before');
        chmod($path, 0751);

        $this->io->filePutContents($path, 'after');
        clearstatcache(true, $path);

        self::assertSame(0751, fileperms($path) & 0777);
        self::assertSame([], glob($this->workingDir . DIRECTORY_SEPARATOR . '*.tmp'));
    }

    private static function removeRecursively(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeRecursively($path . '/' . $entry);
            }
        }

        rmdir($path);
    }

    /**
     * Runs a call whose failure path is expected to emit a PHP warning, so the
     * assertion is about the exception and not about the warning.
     *
     * @param callable():mixed $call
     */
    private function silenced(callable $call): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $call();
        } finally {
            restore_error_handler();
        }
    }
}
