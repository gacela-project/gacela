<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Composer\InstalledVersions;
use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_diff;
use function array_values;
use function bin2hex;
use function chdir;
use function escapeshellarg;
use function fclose;
use function file_put_contents;
use function getcwd;
use function getenv;
use function implode;
use function is_resource;
use function json_encode;
use function mkdir;
use function proc_close;
use function proc_open;
use function putenv;
use function random_bytes;
use function realpath;
use function scandir;
use function str_starts_with;
use function stream_get_contents;
use function strlen;
use function sys_get_temp_dir;
use function var_export;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

/**
 * bin/gacela bootstraps from the project root even when it runs in a
 * subdirectory, so the files the make commands write belong under that root
 * too. Written relative to the working directory, they landed where
 * `list:modules` never looks.
 */
final class MakeFromASubdirectoryTest extends TestCase
{
    private const TEMP_PREFIX = 'gacela-make-from-subdir-';

    private const REPO_ROOT = __DIR__ . '/../../../..';

    private string $appRoot = '';

    private string $subDirectory = '';

    private string $absoluteTarget = '';

    private string $originalCwd = '';

    protected function setUp(): void
    {
        $this->originalCwd = (string)getcwd();
        $this->appRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::TEMP_PREFIX . bin2hex(random_bytes(5));
        $this->subDirectory = $this->appRoot . DIRECTORY_SEPARATOR . 'sub';
        $this->absoluteTarget = $this->appRoot . '-target';

        mkdir($this->subDirectory, 0777, true);
        file_put_contents(
            $this->appRoot . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode(['autoload' => ['psr-4' => [
                'App\\' => 'src/',
                'Elsewhere\\' => $this->absoluteTarget . DIRECTORY_SEPARATOR,
            ]]], JSON_THROW_ON_ERROR),
        );

        chdir($this->subDirectory);

        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        Gacela::resetCache();

        $tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::TEMP_PREFIX;
        if (strlen($this->appRoot) > strlen($tempRoot) && str_starts_with($this->appRoot, $tempRoot)) {
            DirectoryUtil::removeDir($this->appRoot);
            DirectoryUtil::removeDir($this->absoluteTarget);
        }
    }

    public function test_make_module_writes_under_the_project_root(): void
    {
        $tester = new CommandTester(new MakeModuleCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => 'App/Hello']));
        self::assertFileExists($this->inRoot('src', 'Hello', 'HelloFacade.php'));
        self::assertDirectoryDoesNotExist($this->subDirectory . DIRECTORY_SEPARATOR . 'src');
    }

    public function test_the_reported_paths_are_relative_to_the_project_root(): void
    {
        $tester = new CommandTester(new MakeModuleCommand());
        $tester->execute(['path' => 'App/Hello']);

        self::assertStringContainsString("> Path 'src/Hello/HelloFacade.php' created successfully", $tester->getDisplay());
    }

    public function test_an_absolute_psr4_directory_is_written_where_it_points(): void
    {
        $tester = new CommandTester(new MakeModuleCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => 'Elsewhere/Hello']), $tester->getDisplay());
        self::assertFileExists($this->absoluteTarget . DIRECTORY_SEPARATOR . 'Hello' . DIRECTORY_SEPARATOR . 'HelloFacade.php');
        self::assertSame(['composer.json', 'sub'], array_values(array_diff((array)scandir($this->appRoot), ['.', '..'])));
    }

    public function test_make_file_writes_under_the_project_root(): void
    {
        $tester = new CommandTester(new MakeFileCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['path' => 'App/Hello', 'filenames' => ['Facade']]));
        self::assertFileExists($this->inRoot('src', 'Hello', 'HelloFacade.php'));
        self::assertDirectoryDoesNotExist($this->subDirectory . DIRECTORY_SEPARATOR . 'src');
    }

    /**
     * The overwrite check looks where the files are written. Checked against
     * the working directory, it found nothing and replaced the module.
     */
    public function test_a_second_run_from_the_subdirectory_refuses_to_overwrite(): void
    {
        (new CommandTester(new MakeModuleCommand()))->execute(['path' => 'App/Hello']);

        $tester = new CommandTester(new MakeModuleCommand());

        self::assertSame(Command::FAILURE, $tester->execute(['path' => 'App/Hello']));
        self::assertStringContainsString('src/Hello/HelloFacade.php', $tester->getDisplay());
        self::assertStringContainsString('Nothing was written.', $tester->getDisplay());
    }

    public function test_a_dry_run_from_the_subdirectory_sees_the_existing_files(): void
    {
        (new CommandTester(new MakeModuleCommand()))->execute(['path' => 'App/Hello']);

        $tester = new CommandTester(new MakeModuleCommand());
        $tester->execute(['path' => 'App/Hello', '--dry-run' => true, '--force' => true]);

        self::assertStringContainsString("Would replace 'src/Hello/HelloFacade.php'", $tester->getDisplay());
    }

    /**
     * The whole path the issue took: bin/gacela walks up to the nearest
     * vendor/autoload.php, and list:modules then finds what make:module wrote.
     */
    public function test_bin_gacela_from_a_subdirectory_writes_a_module_list_modules_finds(): void
    {
        $vendor = $this->appRoot . DIRECTORY_SEPARATOR . 'vendor';
        mkdir($vendor);
        file_put_contents($vendor . DIRECTORY_SEPARATOR . 'autoload.php', implode("\n", [
            '<?php',
            'require ' . var_export((string)realpath(self::REPO_ROOT . '/vendor/autoload.php'), true) . ';',
            'spl_autoload_register(static function (string $class): void {',
            "    if (!str_starts_with(\$class, 'App\\\\')) { return; }",
            "    \$file = dirname(__DIR__) . '/src/' . str_replace('\\\\', '/', substr(\$class, 4)) . '.php';",
            '    if (is_file($file)) { require $file; }',
            '});',
            '',
        ]));

        $make = $this->runBinGacela('make:module App/Hello');
        self::assertSame(0, $make['exit'], $make['output']);
        self::assertFileExists($this->inRoot('src', 'Hello', 'HelloFacade.php'));

        $list = $this->runBinGacela('list:modules');
        self::assertStringContainsString('App\\Hello', $list['output']);
    }

    #[DataProvider('customVendorDirectories')]
    public function test_a_composer_proxy_uses_the_application_root_with_a_custom_vendor_directory(string $layout): void
    {
        $vendor = $layout === 'external' ? $this->absoluteTarget : $this->inRoot(...explode('/', $layout));
        $proxy = $this->createComposerProxy($vendor, $layout === 'external' ? $vendor : $layout);
        $this->writeApplicationConfig();

        $make = $this->runScript($proxy, 'make:module App/Hello');

        self::assertSame(0, $make['exit'], $make['output']);
        self::assertFileExists($this->inRoot('src', 'Hello', 'HelloFacade.php'));
        self::assertFileExists($this->inRoot('config-read.txt'));
        self::assertDirectoryDoesNotExist($this->subDirectory . DIRECTORY_SEPARATOR . 'src');
        $list = $this->runScript($proxy, 'list:modules');
        self::assertSame(0, $list['exit'], $list['output']);
        self::assertStringContainsString('App\\Hello', $list['output']);
    }

    public function test_a_bundled_composer_loader_does_not_replace_the_proxy_project_root(): void
    {
        $vendor = $this->inRoot('lib');
        $proxy = $this->createComposerProxy($vendor, 'lib', true);
        $this->writeApplicationConfig();

        $make = $this->runScript($proxy, 'make:module App/Hello');

        self::assertSame(0, $make['exit'], $make['output']);
        self::assertFileExists($this->inRoot('src', 'Hello', 'HelloFacade.php'));
        self::assertFileExists($this->inRoot('config-read.txt'));
        self::assertDirectoryDoesNotExist($vendor . DIRECTORY_SEPARATOR . 'tool' . DIRECTORY_SEPARATOR . 'src');
    }

    public function test_the_proxy_autoloader_takes_precedence_over_a_default_vendor_directory(): void
    {
        $proxy = $this->createComposerProxy($this->inRoot('lib'), 'lib');
        mkdir($this->inRoot('vendor'));
        file_put_contents($this->inRoot('vendor', 'autoload.php'), '<?php throw new RuntimeException("Wrong autoloader");');

        $run = $this->runScript($proxy, 'list');

        self::assertSame(0, $run['exit'], $run['output']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function customVendorDirectories(): iterable
    {
        yield 'custom directory' => ['lib'];
        yield 'nested directory' => ['build/dependencies'];
        yield 'external directory' => ['external'];
    }

    private function createComposerProxy(string $vendor, string $configuredDirectory, bool $bundledTool = false): string
    {
        mkdir($vendor . DIRECTORY_SEPARATOR . 'bin', 0777, true);
        file_put_contents($this->inRoot('composer.json'), json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
            'config' => ['vendor-dir' => $configuredDirectory],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($vendor . DIRECTORY_SEPARATOR . 'composer.json', '{"name":"decoy/package"}');
        $this->writeProxyMetadata($vendor);
        if ($bundledTool) {
            $this->writeBundledAutoloader($vendor);
        }

        $this->writeProxyAutoloader($vendor, $bundledTool);
        $proxy = $vendor . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'gacela';
        file_put_contents($proxy, implode("\n", [
            '<?php',
            "\$GLOBALS['_composer_bin_dir'] = __DIR__;",
            "\$GLOBALS['_composer_autoload_path'] = __DIR__ . '/../autoload.php';",
            'include ' . var_export((string)realpath(self::REPO_ROOT . '/bin/gacela'), true) . ';',
        ]));

        return $proxy;
    }

    private function writeApplicationConfig(): void
    {
        file_put_contents($this->inRoot('gacela.php'), <<<'PHP'
            <?php
            return static function (\Gacela\Framework\Bootstrap\GacelaConfig $config): void {
                $config->setFileCache(false);
                file_put_contents(__DIR__ . '/config-read.txt', 'loaded');
            };
            PHP);
    }

    private function writeBundledAutoloader(string $vendor): void
    {
        $tool = $vendor . DIRECTORY_SEPARATOR . 'tool';
        $toolVendor = $tool . DIRECTORY_SEPARATOR . 'vendor';
        mkdir($toolVendor, 0777, true);
        file_put_contents($tool . DIRECTORY_SEPARATOR . 'composer.json', '{"name":"acme/tool","autoload":{"psr-4":{"App\\\\":"src/"}}}');
        $this->writeProxyMetadata($toolVendor, $tool);
        file_put_contents($toolVendor . DIRECTORY_SEPARATOR . 'autoload.php', implode("\n", [
            '<?php',
            '$loader = new \Composer\Autoload\ClassLoader(__DIR__);',
            '$loader->register(true);',
            'return $loader;',
        ]));
    }

    private function writeProxyMetadata(string $vendor, ?string $projectRoot = null): void
    {
        $root = InstalledVersions::getRootPackage();
        $root['install_path'] = $projectRoot ?? $this->appRoot;
        $root['name'] = $projectRoot === null ? 'acme/app' : 'acme/tool';
        $root['type'] = 'project';
        mkdir($vendor . DIRECTORY_SEPARATOR . 'composer');
        file_put_contents($vendor . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.php', '<?php return ' . var_export(['root' => $root, 'versions' => []], true) . ';');
    }

    private function writeProxyAutoloader(string $vendor, bool $bundledTool): void
    {
        file_put_contents($vendor . DIRECTORY_SEPARATOR . 'autoload.php', implode("\n", [
            '<?php',
            'require ' . var_export((string)realpath(self::REPO_ROOT . '/vendor/autoload.php'), true) . ';',
            '$loader = new \Composer\Autoload\ClassLoader(__DIR__);',
            '$loader->addPsr4("App\\\\", ' . var_export($this->inRoot('src'), true) . ');',
            '$loader->register(true);',
            $bundledTool ? 'require ' . var_export($vendor . DIRECTORY_SEPARATOR . 'tool' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php', true) . ';' : '',
            'return $loader;',
        ]));
    }

    private function inRoot(string ...$segments): string
    {
        return $this->appRoot . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * @return array{exit: int, output: string}
     */
    private function runBinGacela(string $arguments): array
    {
        return $this->runScript((string)realpath(self::REPO_ROOT . '/bin/gacela'), $arguments);
    }

    /**
     * @return array{exit: int, output: string}
     */
    private function runScript(string $bin, string $arguments): array
    {

        // Under infection a child inherits XDEBUG_MODE=coverage, which only
        // slows it down. putenv, because Windows has no `VAR=x cmd` prefix.
        $previous = getenv('XDEBUG_MODE');
        putenv('XDEBUG_MODE=off');

        try {
            $process = proc_open(
                escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' ' . $arguments . ' 2>&1',
                [1 => ['pipe', 'w']],
                $pipes,
                $this->subDirectory,
            );
        } finally {
            putenv($previous === false ? 'XDEBUG_MODE' : 'XDEBUG_MODE=' . $previous);
        }

        self::assertTrue(is_resource($process));

        $output = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return ['exit' => proc_close($process), 'output' => $output];
    }
}
