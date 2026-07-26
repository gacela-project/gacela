<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Init;

use Gacela\Console\Infrastructure\Command\InitCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sys_get_temp_dir;
use function unlink;

final class InitCommandTest extends TestCase
{
    private string $appRoot = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/gacela-init-test-' . bin2hex(random_bytes(4));
        mkdir($this->appRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $file = $this->appRoot . '/gacela.php';
        if (is_file($file)) {
            unlink($file);
        }

        if (is_dir($this->appRoot)) {
            rmdir($this->appRoot);
        }
    }

    public function test_writes_a_gacela_php_that_returns_a_callable(): void
    {
        $tester = $this->init($this->appRoot, []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Reports the file it created, and points at the next step. The path is
        // joined with DIRECTORY_SEPARATOR, so assert on the name, not the shape.
        $display = $tester->getDisplay();
        self::assertStringContainsString('Created', $display);
        self::assertStringContainsString('gacela.php', $display);
        self::assertStringContainsString('make:module', $display);

        $returned = require $this->appRoot . '/gacela.php';
        self::assertIsCallable($returned);
    }

    public function test_generated_file_is_valid_for_bootstrap(): void
    {
        $this->init($this->appRoot, []);

        $contents = (string)file_get_contents($this->appRoot . '/gacela.php');

        self::assertStringContainsString('declare(strict_types=1);', $contents);
        self::assertStringContainsString('GacelaConfig', $contents);
    }

    public function test_refuses_to_overwrite_an_existing_file(): void
    {
        file_put_contents($this->appRoot . '/gacela.php', '<?php // mine');

        $tester = $this->init($this->appRoot, []);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('already exists', $tester->getDisplay());
        self::assertStringContainsString('--force', $tester->getDisplay());

        // The point of refusing: the user's file is left untouched.
        self::assertSame('<?php // mine', file_get_contents($this->appRoot . '/gacela.php'));
    }

    public function test_force_overwrites_an_existing_file(): void
    {
        file_put_contents($this->appRoot . '/gacela.php', '<?php // mine');

        $tester = $this->init($this->appRoot, ['--force' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('// mine', (string)file_get_contents($this->appRoot . '/gacela.php'));
    }

    public function test_fails_loudly_when_the_file_cannot_be_written(): void
    {
        $missingRoot = $this->appRoot . '/does-not-exist';

        $this->expectException(RuntimeException::class);
        // The target path is joined with DIRECTORY_SEPARATOR; pin the failure and
        // the file it names, not the separator the host happens to use.
        $this->expectExceptionMessage('gacela.php" was not written');

        // The failing write emits a PHP warning of its own; the assertion is
        // about the exception it is turned into.
        set_error_handler(static fn (): bool => true);

        try {
            $this->init($missingRoot, []);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param array<string, bool> $input
     */
    private function init(string $appRoot, array $input): CommandTester
    {
        $tester = new CommandTester(new InitCommand($appRoot));
        $tester->execute($input);

        return $tester;
    }
}
