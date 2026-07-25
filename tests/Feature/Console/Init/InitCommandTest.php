<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Init;

use Gacela\Console\Infrastructure\Command\InitCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function restore_error_handler;
use function rmdir;
use function rtrim;
use function set_error_handler;
use function sprintf;
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
        self::assertSame([
            '✓ Created ' . $this->appRoot . '/gacela.php',
            '',
            'Next: bin/gacela make:module App/YourModule --minimal',
            '',
        ], self::linesOf($tester));

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
        self::assertSame([
            'gacela.php already exists.',
            'Pass --force to overwrite it.',
            '',
        ], self::linesOf($tester));
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
        $this->expectExceptionMessage(sprintf('File "%s/gacela.php" was not written', $missingRoot));

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
