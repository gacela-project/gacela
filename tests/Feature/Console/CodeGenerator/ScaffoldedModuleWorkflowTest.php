<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function chdir;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function getcwd;
use function getenv;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;
use function mkdir;
use function putenv;
use function random_bytes;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

/**
 * The documented workflow, run against what `make:module` writes rather than
 * against hand-written fixtures: the scaffolder's output is what a new user
 * starts from, and a fixture that differs from it proves nothing about it.
 *
 * Each test scaffolds its own module under its own root namespace in a fresh
 * temp directory, so no test depends on another having run first.
 */
final class ScaffoldedModuleWorkflowTest extends TestCase
{
    private const TEMP_PREFIX = 'gacela-scaffolded-module-';

    private const REPO_ROOT = __DIR__ . '/../../../..';

    private string $appRoot = '';

    private string $originalCwd = '';

    private string $rootNamespace = '';

    /** @var ?callable(string): void */
    private $autoloader;

    protected function setUp(): void
    {
        $this->originalCwd = (string)getcwd();
        $this->rootNamespace = 'ScaffoldedApp' . bin2hex(random_bytes(4));
        $this->appRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::TEMP_PREFIX . bin2hex(random_bytes(5));

        mkdir($this->appRoot);
        file_put_contents(
            $this->appRoot . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode(['autoload' => ['psr-4' => [$this->rootNamespace . '\\' => 'src/']]], JSON_THROW_ON_ERROR),
        );

        // What `composer dump-autoload` would give the project: the scaffolded
        // namespace is not in this repository's autoloader.
        $prefix = $this->rootNamespace . '\\';
        $srcDir = $this->appRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $this->autoloader = static function (string $class) use ($prefix, $srcDir): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $file = $srcDir . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        };
        spl_autoload_register($this->autoloader);

        // The documented commands run from the project root.
        chdir($this->appRoot);

        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if ($this->autoloader !== null) {
            spl_autoload_unregister($this->autoloader);
            $this->autoloader = null;
        }

        $tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::TEMP_PREFIX;
        if (strlen($this->appRoot) > strlen($tempRoot) && str_starts_with($this->appRoot, $tempRoot)) {
            DirectoryUtil::removeDir($this->appRoot);
        }
    }

    #[DataProvider('documentedInvocations')]
    public function test_make_module_writes_every_file_it_reports(array $options, array $pillars): void
    {
        $display = $this->scaffold($options);

        foreach ($pillars as $className) {
            if ($className === null) {
                continue;
            }

            self::assertStringContainsString(sprintf("> Path 'src/Hello/%s.php' created successfully", $className), $display);
            self::assertFileExists(
                $this->appRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Hello' . DIRECTORY_SEPARATOR . $className . '.php',
            );
        }

        self::assertStringContainsString("Module 'Hello' created successfully", $display);
    }

    #[DataProvider('documentedInvocations')]
    public function test_list_modules_finds_the_scaffolded_module_with_its_pillars(array $options, array $pillars): void
    {
        $this->scaffold($options);

        $tester = new CommandTester(new ListModulesCommand());
        self::assertSame(Command::SUCCESS, $tester->execute(['--json' => true]));

        /** @var list<array<string, ?string>> $modules */
        $modules = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([[
            'module' => 'Hello',
            'fullModuleName' => $this->rootNamespace . '\\Hello',
            'facade' => $this->classFor($pillars[0]),
            'factory' => $this->classFor($pillars[1]),
            'config' => $this->classFor($pillars[2]),
            'provider' => $this->classFor($pillars[3]),
        ]], $modules);
    }

    #[DataProvider('documentedInvocations')]
    public function test_the_scaffolded_facade_resolves_its_own_factory_and_config(array $options, array $pillars): void
    {
        $this->scaffold($options);

        $facadeClass = (string)$this->classFor($pillars[0]);
        $facade = new $facadeClass();
        self::assertInstanceOf(AbstractFacade::class, $facade);

        $factory = $facade->getFactory();
        self::assertSame($factory::class, $this->classFor($pillars[1]));

        $config = $factory->getConfig();
        self::assertInstanceOf(AbstractConfig::class, $config);

        if ($pillars[2] !== null) {
            self::assertSame($config::class, $this->classFor($pillars[2]));
        }
    }

    #[DataProvider('documentedInvocations')]
    public function test_the_shipped_phpstan_rules_find_nothing_in_the_scaffolded_module(array $options, array $pillars): void
    {
        $this->scaffold($options);

        $result = $this->runPhpStan();

        self::assertSame(0, $result['totals']['errors'] ?? null, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertSame(0, $result['totals']['file_errors'] ?? null, json_encode($result, JSON_THROW_ON_ERROR));
    }

    /**
     * The two invocations `docs/getting-started.md` shows, with the classes
     * each should write: Facade, Factory, Config, Provider (null when absent).
     *
     * @return iterable<string, array{array<string, bool>, array{string, string, ?string, ?string}}>
     */
    public static function documentedInvocations(): iterable
    {
        yield 'four pillars' => [[], ['HelloFacade', 'HelloFactory', 'HelloConfig', 'HelloProvider']];
        yield 'two-file floor, short names' => [['--minimal' => true, '--short-name' => true], ['Facade', 'Factory', null, null]];
    }

    /**
     * @param array<string, bool> $options
     */
    private function scaffold(array $options): string
    {
        $tester = new CommandTester(new MakeModuleCommand());
        $exitCode = $tester->execute(['path' => $this->rootNamespace . '/Hello'] + $options);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());

        return $tester->getDisplay();
    }

    private function classFor(?string $className): ?string
    {
        return $className === null ? null : $this->rootNamespace . '\\Hello\\' . $className;
    }

    /**
     * @return array<string, mixed>
     */
    private function runPhpStan(): array
    {
        // Forward slashes: neon reads a backslash as an escape.
        $neon = static fn (string $path): string => str_replace('\\', '/', $path);

        $configPath = $this->appRoot . DIRECTORY_SEPARATOR . 'phpstan.neon';
        file_put_contents($configPath, implode("\n", [
            'includes:',
            '    - ' . $neon(self::REPO_ROOT . '/phpstan-gacela.neon'),
            'parameters:',
            '    level: max',
            '    tmpDir: ' . $neon($this->appRoot . '/.phpstan'),
            '    paths:',
            '        - ' . $neon($this->appRoot . '/src'),
            '',
        ]));

        $command = sprintf(
            '%s analyse -c %s --memory-limit=1G --no-progress --error-format=json',
            escapeshellarg(self::REPO_ROOT . '/vendor/bin/phpstan'),
            escapeshellarg($configPath),
        );

        // A child process inherits XDEBUG_MODE=coverage under infection, and
        // PHPStan under coverage runs out of memory. putenv, not a `VAR=x cmd`
        // prefix, because Windows does not understand the prefix.
        $previous = getenv('XDEBUG_MODE');
        putenv('XDEBUG_MODE=off');

        try {
            exec($command, $output);
        } finally {
            putenv($previous === false ? 'XDEBUG_MODE' : 'XDEBUG_MODE=' . $previous);
        }

        $decoded = json_decode(implode("\n", $output), true);
        self::assertIsArray($decoded, 'phpstan did not print JSON: ' . implode("\n", $output));

        return is_array($decoded) ? $decoded : [];
    }
}
