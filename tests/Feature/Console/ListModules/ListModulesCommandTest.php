<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules;

use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

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

        $output = $this->command->getDisplay();
        $namespace = 'GacelaTest\\Feature\\Console\\ListModules';

        // Every discovered module is listed by namespace.
        self::assertStringContainsString($namespace . '\\LevelUp\\TestModule3', $output);
        self::assertStringContainsString($namespace . '\\TestModule1', $output);
        self::assertStringContainsString($namespace . '\\TestModule2', $output);

        // Each row marks the pillars that module actually has: TestModule3 has a
        // Facade, Factory and Config but no Provider; TestModule2 has only a Facade.
        self::assertSame(3, substr_count(self::rowFor($output, 'LevelUp\\TestModule3'), 'x'));
        self::assertSame(1, substr_count(self::rowFor($output, '\\TestModule2'), 'x'));
    }

    public function test_list_modules_detailed(): void
    {
        $this->command->execute(['--detailed' => true]);

        $output = $this->command->getDisplay();
        $namespace = 'GacelaTest\\Feature\\Console\\ListModules';

        // Modules are numbered from 1, in discovery order.
        self::assertStringContainsString('1.- TestModule3', $output);
        self::assertStringContainsString('2.- TestModule1', $output);
        self::assertStringContainsString('3.- TestModule2', $output);
        self::assertStringNotContainsString('0.-', $output);

        // A pillar the module has reports its fully-qualified class name.
        self::assertStringContainsString('Facade: ' . $namespace . '\\TestModule1\\TestModule1Facade', $output);
        self::assertStringContainsString('Provider: ' . $namespace . '\\TestModule1\\TestModule1Provider', $output);

        // A pillar it does not have renders blank instead of a class name:
        // TestModule1 has no Config, and TestModule2 has only a Facade.
        self::assertMatchesRegularExpression('/^Config:\s*$/m', $output);
        self::assertStringNotContainsString('TestModule2Factory', $output);
    }

    public function test_list_modules_not_detailed(): void
    {
        $this->command->execute(['--detailed' => false]);

        $output = $this->command->getDisplay();

        // Verify this is the simple table view (not detailed view)
        self::assertStringContainsString('┌────', $output, 'Should contain table borders');
        self::assertStringNotContainsString('============================', $output, 'Should not contain detailed view separators');
        self::assertStringContainsString('TestModule3', $output);
    }

    #[DataProvider('commandInputProvider')]
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

    public function test_non_matching_filter_reports_no_modules(): void
    {
        $this->command->execute(['filter' => 'NoSuchModuleXYZ']);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('No modules match filter "NoSuchModuleXYZ".', $output);
        self::assertStringNotContainsString('┌────', $output);
    }

    public function test_non_matching_filter_reports_no_modules_in_detailed_view(): void
    {
        $this->command->execute(['filter' => 'NoSuchModuleXYZ', '--detailed' => true]);

        self::assertStringContainsString(
            'No modules match filter "NoSuchModuleXYZ".',
            $this->command->getDisplay(),
        );
    }

    public static function commandInputProvider(): iterable
    {
        yield 'slashes' => ['ListModules/TestModule1'];
        yield 'backward slashes' => ['ListModules\\TestModule1'];
    }

    /**
     * The single table row mentioning $module, so pillar assertions do not depend
     * on how wide the columns render.
     */
    private static function rowFor(string $output, string $module): string
    {
        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, $module)) {
                return $line;
            }
        }

        self::fail(sprintf('No row found for module "%s"', $module));
    }
}
