<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Init;

use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Console\Infrastructure\Command\InitCommand;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * What `init` produces, `doctor` has to be happy with.
 *
 * The two drifted once already: the generated `gacela.php` declared
 * `config/*.php` and nothing created it, so the first `doctor` run on an
 * untouched project reported a config path that loads nothing. A diagnostic
 * that fires on a project nobody has edited yet is how people learn to stop
 * reading diagnostics.
 *
 * Neither side alone can catch that. This asserts the relationship, so a new
 * check that fires on a fresh project fails here rather than in someone's first
 * five minutes with the framework.
 */
final class ScaffoldedProjectIsHealthyTest extends TestCase
{
    private string $appRoot = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/gacela-scaffold-' . bin2hex(random_bytes(4));
        mkdir($this->appRoot . '/vendor', 0o777, true);

        // A real project has one, and `doctor` reads it to check that each
        // package declares what it imports.
        file_put_contents(
            $this->appRoot . '/composer.json',
            '{"name":"acme/scaffolded","autoload":{"psr-4":{"App\\\\":"src/"}}}',
        );
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();

        self::assertStringStartsWith(sys_get_temp_dir() . '/gacela-scaffold-', $this->appRoot);

        // Named one by one: everything here is something this test or the
        // command it ran created, and nothing is swept by pattern.
        foreach (['/gacela.php', '/composer.json', '/config/app.php'] as $relative) {
            $file = $this->appRoot . $relative;
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (['/config', '/vendor', ''] as $relative) {
            $directory = $this->appRoot . $relative;
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function test_a_freshly_scaffolded_project_passes_doctor(): void
    {
        (new CommandTester(new InitCommand($this->appRoot)))->execute([]);

        // No closure: the generated `gacela.php` is the configuration, which is
        // how a scaffolded project is actually bootstrapped.
        Gacela::bootstrap($this->appRoot);

        $tester = new CommandTester(new DoctorCommand());
        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('All checks passed', $display);
        self::assertStringNotContainsString('⚠', $display, 'a scaffolded project should warn about nothing');
        self::assertStringNotContainsString('✗', $display);
    }
}
