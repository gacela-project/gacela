<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugConfig;

use Gacela\Console\Infrastructure\Command\DebugConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugConfigCommandTest extends TestCase
{
    public function test_renders_every_value_type_sorted_by_key(): void
    {
        // Declared out of order on purpose: the report sorts the keys itself.
        $tester = $this->debugConfig([], [
            'zeta' => 'last',
            'alpha' => 'first',
            'a_true' => true,
            'b_false' => false,
            'c_null' => null,
            'd_number' => 42,
            'e_list' => ['a/b', 'c'],
        ]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Each type renders in its literal form rather than PHP's cast of it:
        // an empty cell for false/null would be indistinguishable from a missing key.
        self::assertMatchesRegularExpression('/a_true\s*\|\s*true\b/', $display);
        self::assertMatchesRegularExpression('/b_false\s*\|\s*false\b/', $display);
        self::assertMatchesRegularExpression('/c_null\s*\|\s*null\b/', $display);
        self::assertMatchesRegularExpression('/d_number\s*\|\s*42\b/', $display);

        // Arrays render as JSON, with slashes left unescaped so paths stay readable.
        self::assertStringContainsString('["a/b","c"]', $display);

        // Keys are sorted, though they were declared out of order.
        self::assertLessThan(strpos($display, 'zeta'), strpos($display, 'alpha'));

        self::assertStringContainsString('7 configuration value(s).', $display);
    }

    public function test_filters_keys_by_substring(): void
    {
        // The matching key sorts last, so a skipped key has to be stepped over
        // rather than end the scan.
        $tester = $this->debugConfig(['filter' => 'zeta'], [
            'zeta' => 'last',
            'alpha' => 'first',
        ]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('zeta', $display);
        self::assertStringNotContainsString('alpha', $display);
        self::assertStringContainsString('1 configuration value(s).', $display);
    }

    public function test_reports_when_no_keys_match_the_filter(): void
    {
        $tester = $this->debugConfig(['filter' => 'nonexistent_key_xyz'], ['zeta' => 'last']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'No configuration keys match "nonexistent_key_xyz".',
            $tester->getDisplay(),
        );
    }

    public function test_reports_an_empty_configuration(): void
    {
        $tester = $this->debugConfig([], []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No configuration values found.', $tester->getDisplay());
    }

    /**
     * @param array<string, string> $input
     * @param array<string, mixed> $values
     */
    private function debugConfig(array $input, array $values): CommandTester
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($values): void {
            $config->resetInMemoryCache();
            /** @var mixed $value */
            foreach ($values as $key => $value) {
                $config->addAppConfigKeyValue($key, $value);
            }
        });

        $tester = new CommandTester(new DebugConfigCommand());
        $tester->execute($input);

        return $tester;
    }
}
