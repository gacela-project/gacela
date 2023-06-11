<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules;

use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListModulesCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new ListModulesCommand());
    }

    public function test_list_modules_simple(): void
    {
        $this->command->execute([]);

        $expected = <<<TXT
┌────────────────────────────────────────────────────────────┬────────┬─────────┬────────┬───────────────┐
│ Module namespace                                           │ Facade │ Factory │ Config │ Dep. Provider │
├────────────────────────────────────────────────────────────┼────────┼─────────┼────────┼───────────────┤
│ GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3 │ ✔️     │ ✔️      │ ✔️     │ ✖️            │
│ GacelaTest\Feature\Console\ListModules\TestModule1         │ ✔️     │ ✔️      │ ✖️     │ ✔️            │
│ GacelaTest\Feature\Console\ListModules\TestModule2         │ ✔️     │ ✖️      │ ✖️     │ ✖️            │
└────────────────────────────────────────────────────────────┴────────┴─────────┴────────┴───────────────┘

TXT;
        self::assertSame($expected, $this->command->getDisplay());
    }

    public function test_list_modules(): void
    {
        $this->command->execute(['--detailed' => null]);

        $expected = <<<TXT
============================
1.- TestModule3
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Facade
Factory: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Factory
Config: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Config
DependencyProvider: ✖️
============================
2.- TestModule1
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Facade
Factory: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Factory
Config: ✖️
DependencyProvider: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1DependencyProvider
============================
3.- TestModule2
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule2\TestModule2Facade
Factory: ✖️
Config: ✖️
DependencyProvider: ✖️

TXT;
        self::assertSame($expected, $this->command->getDisplay());
    }

    /**
     * @dataProvider commandInputProvider
     */
    public function test_list_modules_with_filter(string $input): void
    {
        $this->command->execute(['filter' => $input]);

        $out = $this->command->getDisplay();

        self::assertStringContainsString('TestModule1', $out);
        self::assertStringNotContainsString('TestModule2', $out);
        self::assertStringNotContainsString('TestModule3', $out);
        self::assertStringNotContainsString('vendor', $out);
        self::assertStringNotContainsString('ToBeIgnored', $out);
    }

    public function test_list_modules_all(): void
    {
        Gacela::bootstrap(__DIR__);

        $input = new StringInput('list:modules --all');
        $output = new BufferedOutput();

        $bootstrap = new ConsoleBootstrap();
        $bootstrap->setAutoExit(false);
        $bootstrap->run($input, $output);

        $out = $output->fetch();

        self::assertMatchesRegularExpression('#TestModule1.*ListModules\\\TestModule1#', $out);
        self::assertMatchesRegularExpression('#TestModule2.*ListModules\\\TestModule2#', $out);
        self::assertMatchesRegularExpression('#TestModule3.*ListModules\\\LevelUp\\\TestModule3#', $out);
        self::assertStringNotContainsString('vendor', $out);
        self::assertStringNotContainsString('ToBeIgnored', $out);
    }

    public function commandInputProvider(): iterable
    {
        yield 'slashes' => ['ListModules/TestModule1'];
        yield 'backward slashes' => ['ListModules\\TestModule1'];
    }
}
