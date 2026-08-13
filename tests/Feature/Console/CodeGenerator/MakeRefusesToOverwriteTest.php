<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\ConsoleBootstrap;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_get_contents;
use function file_put_contents;
use function unlink;

/**
 * Generating over a module that already exists.
 *
 * `make:module App/Wallet` on an existing module replaced all four pillars with
 * stubs and reported "created successfully" for each one. No prompt, no flag, no
 * mention that anything had been there -- and the only record that it had was
 * the file that was just overwritten.
 *
 * The rule is the one the unusable-path check already follows: decide before
 * writing anything. A module half replaced is worse than one refused.
 */
final class MakeRefusesToOverwriteTest extends TestCase
{
    private const MODULE_ARG = 'Psr4CodeGeneratorData/OverwriteModule';

    private const MODULE_DIR = '.' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'OverwriteModule';

    private const FACADE = self::MODULE_DIR . DIRECTORY_SEPARATOR . 'OverwriteModuleFacade.php';

    private const HAND_WRITTEN = '<?php // hand written, not a stub';

    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(self::MODULE_DIR);
    }

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
        DirectoryUtil::removeDir(self::MODULE_DIR);
    }

    public function test_a_second_run_refuses_and_leaves_the_files_alone(): void
    {
        $this->generateModule();
        file_put_contents(self::FACADE, self::HAND_WRITTEN);

        $output = $this->runCommand('make:module ' . self::MODULE_ARG);

        self::assertStringContainsString('already exist.', $output);
        self::assertStringContainsString('Nothing was written.', $output);
        self::assertSame(self::HAND_WRITTEN, file_get_contents(self::FACADE));
    }

    /**
     * The refusal names the files, because "it already exists" without saying
     * which leaves the reader to guess what a `--force` would cost them.
     */
    public function test_the_refusal_names_every_file_that_blocks_it(): void
    {
        $this->generateModule();

        $output = $this->runCommand('make:module ' . self::MODULE_ARG);

        foreach (['Facade', 'Factory', 'Config', 'Provider'] as $pillar) {
            self::assertStringContainsString('OverwriteModule' . $pillar . '.php', $output);
        }
    }

    public function test_force_replaces_them(): void
    {
        $this->generateModule();
        file_put_contents(self::FACADE, self::HAND_WRITTEN);

        $output = $this->runCommand('make:module ' . self::MODULE_ARG . ' --force');

        self::assertStringContainsString('created successfully', $output);
        self::assertNotSame(self::HAND_WRITTEN, file_get_contents(self::FACADE));
    }

    /**
     * Only the files that are actually in the way. A module missing one pillar
     * still reports the three that are there, so the reader knows a `--force`
     * would not be free.
     */
    public function test_only_the_files_that_exist_are_reported(): void
    {
        $this->generateModule();
        unlink(self::MODULE_DIR . DIRECTORY_SEPARATOR . 'OverwriteModuleConfig.php');

        $output = $this->runCommand('make:module ' . self::MODULE_ARG);

        self::assertStringContainsString('OverwriteModuleFacade.php', $output);
        self::assertStringNotContainsString('OverwriteModuleConfig.php', $output);
    }

    public function test_make_file_refuses_the_same_way(): void
    {
        $this->generateModule();
        file_put_contents(self::FACADE, self::HAND_WRITTEN);

        $output = $this->runCommand('make:file ' . self::MODULE_ARG . ' Facade');

        self::assertStringContainsString('already exists.', $output);
        self::assertSame(self::HAND_WRITTEN, file_get_contents(self::FACADE));
    }

    /**
     * One file reads as one file. The plural form on a single path is the kind
     * of seam that says nobody ran it.
     */
    public function test_one_file_is_reported_in_the_singular(): void
    {
        $this->generateModule();

        $output = $this->runCommand('make:file ' . self::MODULE_ARG . ' Facade');

        self::assertStringContainsString('already exists.', $output);
        self::assertStringContainsString('to replace it.', $output);
    }

    public function test_many_files_are_reported_in_the_plural(): void
    {
        $this->generateModule();

        $output = $this->runCommand('make:module ' . self::MODULE_ARG);

        self::assertStringContainsString('already exist.', $output);
        self::assertStringContainsString('to replace them.', $output);
    }

    /**
     * The check must not cost the first run anything: a module that does not
     * exist yet is still generated in full.
     */
    public function test_a_first_run_is_unaffected(): void
    {
        $output = $this->runCommand('make:module ' . self::MODULE_ARG);

        self::assertStringContainsString('created successfully', $output);
        self::assertStringNotContainsString('Nothing was written', $output);
        self::assertFileExists(self::FACADE);
    }

    private function generateModule(): void
    {
        $this->runCommand('make:module ' . self::MODULE_ARG);
    }

    private function runCommand(string $command): string
    {
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run(new StringInput($command), $output);

        return $output->fetch();
    }
}
