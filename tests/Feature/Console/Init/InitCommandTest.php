<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Init;

use Gacela\Console\Infrastructure\Command\InitCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
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
        $command = new CommandTester(new InitCommand($this->appRoot));

        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($this->appRoot . '/gacela.php');

        $returned = require $this->appRoot . '/gacela.php';
        self::assertIsCallable($returned);
    }

    public function test_generated_file_is_valid_for_bootstrap(): void
    {
        (new CommandTester(new InitCommand($this->appRoot)))->execute([]);

        $contents = (string)file_get_contents($this->appRoot . '/gacela.php');

        self::assertStringContainsString('declare(strict_types=1);', $contents);
        self::assertStringContainsString('GacelaConfig', $contents);
    }

    public function test_refuses_to_overwrite_an_existing_file(): void
    {
        file_put_contents($this->appRoot . '/gacela.php', '<?php // mine');

        $command = new CommandTester(new InitCommand($this->appRoot));
        $exitCode = $command->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('already exists', $command->getDisplay());
        self::assertSame('<?php // mine', file_get_contents($this->appRoot . '/gacela.php'));
    }

    public function test_force_overwrites_an_existing_file(): void
    {
        file_put_contents($this->appRoot . '/gacela.php', '<?php // mine');

        $command = new CommandTester(new InitCommand($this->appRoot));
        $exitCode = $command->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('// mine', (string)file_get_contents($this->appRoot . '/gacela.php'));
    }
}
