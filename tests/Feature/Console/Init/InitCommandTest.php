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
        // Named one by one rather than swept: every one of these is a file this
        // test knew it was creating, and the root is asserted to be the temp
        // directory it made before anything is removed under it.
        self::assertStringStartsWith(sys_get_temp_dir() . '/gacela-init-test-', $this->appRoot);

        foreach (['/gacela.php', '/config/app.php', '/composer.json'] as $relative) {
            $file = $this->appRoot . $relative;
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (['/config', ''] as $relative) {
            $directory = $this->appRoot . $relative;
            if (is_dir($directory)) {
                rmdir($directory);
            }
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

    /**
     * The generated `gacela.php` declares `config/*.php`. Without a file behind
     * it the first `doctor` run on a freshly scaffolded project reports a config
     * path that loads nothing -- true, and entirely the scaffolder's doing.
     */
    public function test_writes_the_config_file_the_generated_gacela_php_declares(): void
    {
        $tester = $this->init($this->appRoot, []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists($this->appRoot . '/config/app.php');

        /** @var mixed $values */
        $values = require $this->appRoot . '/config/app.php';
        self::assertSame([], $values);
    }

    public function test_names_the_config_file_it_created(): void
    {
        $tester = $this->init($this->appRoot, []);

        // The command joins the path with DIRECTORY_SEPARATOR, so asserting a
        // forward slash passes on POSIX and fails on windows -- and its
        // negation in the --force test below would have passed there for the
        // wrong reason, asserting nothing at all.
        self::assertStringContainsString($this->configPath(), $tester->getDisplay());
    }

    /**
     * `--force` is about regenerating `gacela.php`. The configuration beside it
     * is the project's own, and a scaffolder that overwrote it would destroy
     * work no one asked it to touch.
     */
    public function test_force_leaves_an_existing_config_file_alone(): void
    {
        mkdir($this->appRoot . '/config', 0777, true);
        file_put_contents($this->appRoot . '/config/app.php', "<?php return ['mine' => true];");

        $tester = $this->init($this->appRoot, ['--force' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(
            "<?php return ['mine' => true];",
            file_get_contents($this->appRoot . '/config/app.php'),
        );
        self::assertStringNotContainsString($this->configPath(), $tester->getDisplay());
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
     * The generated `setProjectNamespaces()` used to be the literal `['App']`
     * for every project — right for the ones that use it, quietly wrong for the
     * rest.
     *
     * It is not decoration. The resolver builds
     * `\{projectNamespace}\{Module}\{Module}Factory` from these and tries them
     * *before* the module's own namespace, so a wrong prefix costs a failed
     * lookup on every cold resolution, and resolves the wrong class outright
     * for a project that does have an `App\` module of the same name.
     */
    public function test_the_generated_config_names_the_projects_own_namespace(): void
    {
        $this->writeComposerJson(['autoload' => ['psr-4' => ['Acme\\' => 'src']]]);

        $this->init($this->appRoot, []);

        self::assertStringContainsString(
            "setProjectNamespaces(['Acme'])",
            (string)file_get_contents($this->appRoot . '/gacela.php'),
        );
    }

    public function test_every_declared_prefix_is_named(): void
    {
        $this->writeComposerJson(['autoload' => ['psr-4' => ['Acme\\' => 'src', 'Shop\\' => 'shop']]]);

        $this->init($this->appRoot, []);

        self::assertStringContainsString(
            "setProjectNamespaces(['Acme', 'Shop'])",
            (string)file_get_contents($this->appRoot . '/gacela.php'),
        );
    }

    /**
     * `App` is the convention, and a better guess than an empty list — which
     * would read as a decision the project had made.
     */
    public function test_a_project_with_no_manifest_falls_back_to_the_convention(): void
    {
        $this->init($this->appRoot, []);

        self::assertStringContainsString(
            "setProjectNamespaces(['App'])",
            (string)file_get_contents($this->appRoot . '/gacela.php'),
        );
    }

    public function test_a_manifest_declaring_no_autoloading_falls_back_too(): void
    {
        $this->writeComposerJson(['name' => 'acme/app']);

        $this->init($this->appRoot, []);

        self::assertStringContainsString(
            "setProjectNamespaces(['App'])",
            (string)file_get_contents($this->appRoot . '/gacela.php'),
        );
    }

    /**
     * The suggested next command has to be one that works here: `App/YourModule`
     * scaffolds into a namespace composer does not map.
     */
    public function test_the_next_step_names_the_projects_own_namespace(): void
    {
        $this->writeComposerJson(['autoload' => ['psr-4' => ['Acme\\' => 'src']]]);

        $tester = $this->init($this->appRoot, []);

        self::assertStringContainsString('make:module Acme/YourModule', $tester->getDisplay());
    }

    /**
     * As the command spells it, so a mismatch is a real difference rather than
     * a separator.
     */
    private function configPath(): string
    {
        return 'config' . DIRECTORY_SEPARATOR . 'app.php';
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeComposerJson(array $manifest): void
    {
        file_put_contents(
            $this->appRoot . '/composer.json',
            json_encode($manifest, JSON_THROW_ON_ERROR),
        );
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
