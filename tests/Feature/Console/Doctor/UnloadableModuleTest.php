<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor;

use Composer\Autoload\ClassLoader;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UnloadableModuleTest extends TestCase
{
    #[DataProvider('brokenClasses')]
    public function test_discovery_and_doctor_keep_working_beside_an_unloadable_module(string $body, string $message, bool $eventsFirst = false): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-unloadable-' . bin2hex(random_bytes(4));
        $namespace = 'UnloadableProject' . bin2hex(random_bytes(4));
        mkdir($root . DIRECTORY_SEPARATOR . 'Broken', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'Healthy');
        $path = $root . DIRECTORY_SEPARATOR . 'Broken' . DIRECTORY_SEPARATOR . 'BrokenFacade.php';
        file_put_contents($path, '<?php' . "\nnamespace " . $namespace . '\\Broken;' . "\n" . $body);
        file_put_contents(
            $root . DIRECTORY_SEPARATOR . 'Healthy' . DIRECTORY_SEPARATOR . 'HealthyFacade.php',
            '<?php namespace ' . $namespace . '\\Healthy; final class HealthyFacade extends \\Gacela\\Framework\\AbstractFacade {}',
        );
        $loader = new ClassLoader();
        $loader->addPsr4($namespace . '\\', $root);
        $loader->register();

        try {
            Gacela::bootstrap($root, static function (GacelaConfig $config) use ($namespace): void {
                $config->resetInMemoryCache();
                $config->setFileCache(false);
                $config->setProjectNamespaces([$namespace]);
            });
            if ($eventsFirst) {
                self::assertSame([], (new ConsoleFacade())->findProjectEventClasses());
            }

            $list = new CommandTester(new ListModulesCommand());
            $list->execute([]);
            self::assertSame(Command::SUCCESS, $list->getStatusCode());
            self::assertStringContainsString('Healthy', $list->getDisplay());
            self::assertStringNotContainsString('Broken', $list->getDisplay());
            self::assertSame([], (new ConsoleFacade())->findProjectEventClasses());

            $doctor = new CommandTester(new DoctorCommand());
            $doctor->execute(['--json' => true, '--strict' => true]);
            /** @var array{checks: list<array{name: string, status: string, details: list<string>}>} $report */
            $report = json_decode($doctor->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($report);
            self::assertSame(Command::FAILURE, $doctor->getStatusCode());
            $checks = array_column($report['checks'], null, 'name');
            self::assertSame('warn', $checks['undiscovered facades']['status']);
            self::assertStringContainsString($message, implode(' ', $checks['undiscovered facades']['details']));
            self::assertStringContainsString($path . ':3', implode(' ', $checks['undiscovered facades']['details']));
            self::assertArrayHasKey('IDE metadata', $checks);
            Gacela::bootstrap($root, static function (GacelaConfig $config) use ($namespace): void {
                $config->resetInMemoryCache();
                $config->setFileCache(false);
                $config->setProjectNamespaces([$namespace]);
            });
            $list->execute([]);
            self::assertStringNotContainsString('Broken', $list->getDisplay());
            unset($checks['undiscovered facades']);
            self::assertSame(['ok'], array_values(array_unique(array_column($checks, 'status'))));
        } finally {
            $loader->unregister();
            DirectoryUtil::removeDir($root);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2?: bool}>
     */
    public static function brokenClasses(): iterable
    {
        yield 'syntax error' => ['class BrokenFacade extends \\Gacela\\Framework\\AbstractFacade { public function x(): void { if ( } } // GacelaEventInterface', 'Unclosed'];
        yield 'error after declaration' => ['class BrokenFacade extends \\Gacela\\Framework\\AbstractFacade {} strlen([]); // GacelaEventInterface', 'strlen()'];
        yield 'event scan first' => ['class BrokenFacade extends \\Gacela\\Framework\\AbstractFacade {} strlen([]); // GacelaEventInterface', 'strlen()', true];
        yield 'missing parent' => ['class BrokenFacade extends MissingParent {} // GacelaEventInterface', 'MissingParent'];
    }
}
