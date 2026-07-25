<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugConfig;

use Gacela\Console\Infrastructure\Command\DebugConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function explode;
use function rtrim;

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

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '+----------+-------------+',
            '| Key      | Value       |',
            '+----------+-------------+',
            '| a_true   | true        |',
            '| alpha    | first       |',
            '| b_false  | false       |',
            '| c_null   | null        |',
            '| d_number | 42          |',
            '| e_list   | ["a/b","c"] |',
            '| zeta     | last        |',
            '+----------+-------------+',
            '',
            '7 configuration value(s).',
            '',
        ], self::linesOf($tester));
    }

    public function test_filters_keys_by_substring(): void
    {
        // The matching key sorts last, so a skipped key has to be stepped over
        // rather than end the scan.
        $tester = $this->debugConfig(['filter' => 'zeta'], [
            'zeta' => 'last',
            'alpha' => 'first',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            '+------+-------+',
            '| Key  | Value |',
            '+------+-------+',
            '| zeta | last  |',
            '+------+-------+',
            '',
            '1 configuration value(s).',
            '',
        ], self::linesOf($tester));
    }

    public function test_reports_when_no_keys_match_the_filter(): void
    {
        $tester = $this->debugConfig(['filter' => 'nonexistent_key_xyz'], ['zeta' => 'last']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'No configuration keys match "nonexistent_key_xyz".',
            '',
        ], self::linesOf($tester));
    }

    public function test_reports_an_empty_configuration(): void
    {
        $tester = $this->debugConfig([], []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'No configuration values found.',
            '',
        ], self::linesOf($tester));
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

    /**
     * @return list<string>
     */
    private static function linesOf(CommandTester $tester): array
    {
        return array_map(
            static fn (string $line): string => rtrim($line),
            explode("\n", $tester->getDisplay()),
        );
    }
}
