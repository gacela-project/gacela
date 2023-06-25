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
        $this->command->execute(['--simple' => null]);

        $expected = <<<TXT
1.- TestModule3
2.- TestModule1
3.- TestModule2

TXT;
        self::assertSame($expected, $this->command->getDisplay());
    }

    public function test_list_modules(): void
    {
        $this->command->execute([]);

        $expected = <<<TXT
============================
1.- TestModule3
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Facade
Factory: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Factory
Config: GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3\TestModule3Config
DependencyProvider: None
============================
2.- TestModule1
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Facade
Factory: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Factory
Config: None
DependencyProvider: GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1DependencyProvider
============================
3.- TestModule2
----------------------------
Facade: GacelaTest\Feature\Console\ListModules\TestModule2\TestModule2Facade
Factory: None
Config: None
DependencyProvider: None

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

    public function commandInputProvider(): iterable
    {
        yield 'slashes' => ['ListModules/TestModule1'];
        yield 'backward slashes' => ['ListModules\\TestModule1'];
    }
}
