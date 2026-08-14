<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Stubs;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Console\Infrastructure\Command\StubsPublishCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

final class StubsPublishCommandTest extends TestCase
{
    private const GENERATED_DIR = __DIR__ . '/../../../../data/StubbedModule';

    private string $appRoot = '';

    private string $stubsDir = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/gacela-stubs-app-' . bin2hex(random_bytes(6));
        $this->stubsDir = $this->appRoot . '/stubs/gacela';
        mkdir($this->appRoot, 0777, true);

        $this->bootstrap();
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->appRoot);
        $this->removeRecursively(self::GENERATED_DIR);
        Gacela::resetCache();
    }

    public function test_it_publishes_every_stub(): void
    {
        $tester = $this->execute(new StubsPublishCommand());

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists($this->stubsDir . '/facade-maker.txt');
        self::assertFileExists($this->stubsDir . '/factory-maker.txt');
        self::assertFileExists($this->stubsDir . '/service/facade-maker.txt');
    }

    public function test_it_publishes_only_the_asked_template_set(): void
    {
        $tester = $this->execute(new StubsPublishCommand(), ['--template' => 'basic']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists($this->stubsDir . '/facade-maker.txt');
        self::assertFileDoesNotExist($this->stubsDir . '/service/facade-maker.txt');
    }

    public function test_an_unknown_template_set_is_refused(): void
    {
        $tester = $this->execute(new StubsPublishCommand(), ['--template' => 'nope']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Unknown template "nope"', $tester->getDisplay());
    }

    /**
     * A published stub is a file somebody changed on purpose. Overwriting it
     * silently is the one thing this must never do.
     */
    public function test_it_refuses_to_overwrite_what_the_project_already_published(): void
    {
        $this->publishStub('facade-maker.txt', 'house style');

        $tester = $this->execute(new StubsPublishCommand());

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Already published:', $tester->getDisplay());
        self::assertSame('house style', (string)file_get_contents($this->stubsDir . '/facade-maker.txt'));
    }

    /**
     * `stubs:publish` writes into somebody's project and `--force` replaces
     * files they edited on purpose, so saying what it would do is worth having.
     */
    public function test_a_dry_run_names_the_stubs_and_writes_none_of_them(): void
    {
        $tester = $this->execute(new StubsPublishCommand(), ['--dry-run' => true]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Would publish', $display);
        self::assertStringContainsString('Dry run: nothing was written', $display);
        self::assertDirectoryDoesNotExist($this->stubsDir);
    }

    /**
     * The preview names what the real run writes. Asserting the preview alone
     * would pass for one that invented the list.
     */
    public function test_the_dry_run_names_the_stubs_the_real_run_publishes(): void
    {
        $preview = $this->execute(new StubsPublishCommand(), ['--dry-run' => true]);
        $real = $this->execute(new StubsPublishCommand());

        self::assertSame(
            $this->stubNamesIn($preview->getDisplay()),
            $this->stubNamesIn($real->getDisplay()),
        );
    }

    /**
     * Not overwriting is the default on the facade too, not only on the command
     * that happens to pass the flag every time. A published stub is a file
     * somebody changed on purpose, and the default is the safety net.
     */
    public function test_the_facade_does_not_overwrite_unless_asked(): void
    {
        $this->publishStub('facade-maker.txt', 'house style');

        $result = (new ConsoleFacade())->publishStubs($this->stubsDir);

        self::assertContains($this->stubsDir . '/facade-maker.txt', $result->skipped);
        self::assertSame('house style', (string)file_get_contents($this->stubsDir . '/facade-maker.txt'));
    }

    public function test_force_replaces_them(): void
    {
        $this->publishStub('facade-maker.txt', 'house style');

        $tester = $this->execute(new StubsPublishCommand(), ['--force' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertNotSame('house style', (string)file_get_contents($this->stubsDir . '/facade-maker.txt'));
    }

    /**
     * Generation happens where the scaffolder puts files -- relative to the
     * working directory, into the `data/` psr-4 target this repository maps --
     * so this half bootstraps the repository itself and points it at a stubs
     * directory of its own.
     */
    public function test_a_published_stub_is_what_make_module_generates_from(): void
    {
        $this->bootstrapRepositoryWithStubsDir();
        $this->publishStub('facade-maker.txt', "<?php\n\nnamespace \$NAMESPACE\$;\n\n// house style \$CLASS_NAME\$\n");

        $this->execute(new MakeModuleCommand(), ['path' => 'Psr4StubsData/StubbedModule']);

        $generated = (string)file_get_contents(self::GENERATED_DIR . '/StubbedModuleFacade.php');

        self::assertStringContainsString('// house style StubbedModuleFacade', $generated);
    }

    /**
     * Publishing one file must not freeze the rest at the version it was copied
     * from.
     */
    public function test_the_unpublished_files_still_come_from_the_built_in_stubs(): void
    {
        $this->bootstrapRepositoryWithStubsDir();
        $this->publishStub('facade-maker.txt', "<?php\n\nnamespace \$NAMESPACE\$;\n\n// house style \$CLASS_NAME\$\n");

        $this->execute(new MakeModuleCommand(), ['path' => 'Psr4StubsData/StubbedModule']);

        $factory = (string)file_get_contents(self::GENERATED_DIR . '/StubbedModuleFactory.php');

        self::assertStringContainsString('extends AbstractFactory', $factory);
    }

    public function test_doctor_reports_a_stub_that_lost_a_placeholder(): void
    {
        $this->publishStub('facade-maker.txt', '<?php // nothing to substitute');

        $tester = $this->execute(new DoctorCommand());

        self::assertStringContainsString('published stubs', $tester->getDisplay());
        self::assertStringContainsString('$NAMESPACE$', $tester->getDisplay());
    }

    /**
     * An edit filed under a name nothing reads is an edit that never takes
     * effect, and looks exactly like one that did.
     */
    public function test_doctor_reports_a_stub_nothing_reads(): void
    {
        $this->publishStub('facade-maker.txt', '<?php namespace $NAMESPACE$; class $CLASS_NAME$ {}');
        $this->publishStub('facade-makr.txt', '<?php // typo in the filename');

        $tester = $this->execute(new DoctorCommand());

        self::assertStringContainsString('facade-makr.txt', $tester->getDisplay());
        self::assertStringContainsString('matches no template', $tester->getDisplay());
    }

    /**
     * The scan has to descend one level: the service set lives in a
     * subdirectory, so a typo there is exactly as invisible as one at the top.
     */
    public function test_doctor_reports_a_stub_nothing_reads_in_a_subdirectory(): void
    {
        $this->publishStub('service/facade-makr.txt', '<?php // typo, one level down');

        $tester = $this->execute(new DoctorCommand());

        self::assertStringContainsString('service/facade-makr.txt', $tester->getDisplay());
        self::assertStringContainsString('matches no template', $tester->getDisplay());
    }

    public function test_doctor_accepts_a_published_service_stub(): void
    {
        $this->publishStub('service/facade-maker.txt', '<?php namespace $NAMESPACE$; class $CLASS_NAME$ {}');

        $tester = $this->execute(new DoctorCommand());

        self::assertStringContainsString('1 published stub(s), all usable', $tester->getDisplay());
    }

    public function test_doctor_is_quiet_when_nothing_is_published(): void
    {
        $tester = $this->execute(new DoctorCommand());

        self::assertStringContainsString('no stubs published', $tester->getDisplay());
    }

    /**
     * The repository is its own scaffolding target: `data/` is a psr-4 path
     * here, and the generator writes relative to the working directory.
     */
    /**
     * The stub filenames a run reported, whichever verb reported them.
     *
     * @return list<string>
     */
    private function stubNamesIn(string $display): array
    {
        preg_match_all('/([\w-]+\.txt)/', $display, $matches);

        return $matches[1];
    }

    private function bootstrapRepositoryWithStubsDir(): void
    {
        $stubsDir = $this->stubsDir;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($stubsDir): void {
            $config->resetInMemoryCache();
            $config->setStubsDir($stubsDir);
        });
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(Command $command, array $input = []): CommandTester
    {
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private function publishStub(string $relativePath, string $contents): void
    {
        $path = $this->stubsDir . '/' . $relativePath;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function bootstrap(): void
    {
        file_put_contents($this->appRoot . '/composer.json', '{"autoload":{"psr-4":{"App\\\\":"src/"}}}');

        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    private function removeRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeRecursively($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
