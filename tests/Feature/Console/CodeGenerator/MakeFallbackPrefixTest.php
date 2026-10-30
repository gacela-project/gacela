<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Composer\Autoload\ClassLoader;
use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeFallbackPrefixTest extends TestCase
{
    /**
     * @param list<string> $pillars
     */
    #[DataProvider('fallbackCommands')]
    public function test_fallback_commands_generate_classes_composer_can_load(string $kind, string $module, array $pillars): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-fallback-' . bin2hex(random_bytes(4));
        $prefix = 'Fallback' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'composer.json', '{"autoload":{"psr-4":{"":"src/"}}}');
        $loader = new ClassLoader();
        $loader->addPsr4('', $root . DIRECTORY_SEPARATOR . 'src');
        $loader->register();

        try {
            Gacela::bootstrap($root, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache();
                $config->setFileCache(false);
            });
            $tester = new CommandTester($kind === 'module' ? new MakeModuleCommand() : new MakeFileCommand());
            $arguments = ['path' => $prefix . '/' . $module];
            if ($kind === 'file') {
                $arguments['filenames'] = $pillars;
            }

            self::assertSame(Command::SUCCESS, $tester->execute($arguments));
            foreach ($pillars as $pillar) {
                $class = $prefix . '\\' . str_replace('/', '\\', $module) . '\\' . basename($module) . $pillar;
                $file = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
                self::assertFileExists($file);
                self::assertTrue(class_exists($class));
                self::assertSame(realpath($file), (new ReflectionClass($class))->getFileName());
            }
        } finally {
            $loader->unregister();
            DirectoryUtil::removeDir($root);
        }
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function fallbackCommands(): iterable
    {
        yield 'module' => ['module', 'Blog', ['Facade', 'Factory', 'Config', 'Provider']];
        yield 'nested module' => ['module', 'Shop/Cart', ['Facade', 'Factory', 'Config', 'Provider']];
        yield 'file' => ['file', 'Blog', ['Config']];
    }
}
