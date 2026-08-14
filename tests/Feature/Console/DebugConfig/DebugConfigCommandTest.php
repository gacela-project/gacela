<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugConfig;

use Gacela\Console\Infrastructure\Command\DebugConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

final class DebugConfigCommandTest extends TestCase
{
    public function test_an_unknown_format_is_refused_rather_than_answered_with_text(): void
    {
        $tester = $this->debugConfig(['--format' => 'xml'], ['alpha' => 'first']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Unknown format "xml". Use one of: text, json', $tester->getDisplay());
        self::assertStringNotContainsString('alpha', $tester->getDisplay());
    }

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
     * The point of a document: `true` comes back a boolean and `42` an int.
     * The table stringifies everything because a cell is text, and diffing two
     * environments on stringified values turns every type difference into a
     * text difference.
     */
    public function test_json_carries_the_values_as_their_own_types(): void
    {
        $entries = $this->debugConfigAsJson([], [
            'a_true' => true,
            'b_false' => false,
            'c_null' => null,
            'd_number' => 42,
            'e_list' => ['a/b', 'c'],
        ]);

        self::assertTrue($this->entry($entries, 'a_true')['value']);
        self::assertFalse($this->entry($entries, 'b_false')['value']);
        self::assertNull($this->entry($entries, 'c_null')['value']);
        self::assertSame(42, $this->entry($entries, 'd_number')['value']);
        self::assertSame(['a/b', 'c'], $this->entry($entries, 'e_list')['value']);
    }

    public function test_json_is_sorted_by_key_like_the_table(): void
    {
        $entries = $this->debugConfigAsJson([], ['zeta' => 'last', 'alpha' => 'first']);

        self::assertSame(['alpha', 'zeta'], array_column($entries, 'key'));
    }

    /**
     * A key the schema declares and one it does not are different facts about
     * the same value, and the document says which without a reader matching on
     * a colour.
     */
    public function test_json_marks_each_key_against_the_schema(): void
    {
        $entries = $this->debugConfigAsJson([], ['declared_key' => 'yes', 'stray_key' => 'no'], [
            'declared_key' => ConfigType::string(),
        ]);

        self::assertSame('declared', $this->entry($entries, 'declared_key')['schema']);
        self::assertSame('undeclared', $this->entry($entries, 'stray_key')['schema']);
    }

    /**
     * The drift worth catching in CI: a key the schema declares that no source
     * provides. It has no value to list, so it is the one kind of drift a
     * report built only from the values cannot show.
     */
    public function test_json_reports_a_declared_key_nothing_provides(): void
    {
        $entries = $this->debugConfigAsJson([], ['present' => 'here'], [
            'present' => ConfigType::string(),
            'absent' => ConfigType::string()->required(),
        ]);

        $missing = $this->entry($entries, 'absent');

        self::assertSame('missing', $missing['schema']);
        self::assertNull($missing['value']);
    }

    public function test_json_narrows_to_the_filter(): void
    {
        $entries = $this->debugConfigAsJson(['filter' => 'zeta'], ['zeta' => 'last', 'alpha' => 'first']);

        self::assertSame(['zeta'], array_column($entries, 'key'));
    }

    /**
     * A filter matching nothing is an empty list, not the sentence the table
     * gives: a consumer piping this to a parser got a syntax error exactly when
     * the answer was "none".
     */
    public function test_a_filter_matching_nothing_is_an_empty_document(): void
    {
        $tester = $this->debugConfig(
            ['filter' => 'nonexistent_key_xyz', '--json' => true],
            ['zeta' => 'last'],
        );

        self::assertSame([], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('No configuration keys match', $tester->getDisplay());
    }

    public function test_the_json_flag_and_the_format_option_produce_one_document(): void
    {
        $values = ['zeta' => 'last', 'alpha' => 'first'];

        self::assertSame(
            $this->debugConfig(['--json' => true], $values)->getDisplay(),
            $this->debugConfig(['--format' => 'json'], $values)->getDisplay(),
        );
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $values
     * @param array<string, mixed> $schema
     *
     * @return list<array{key: string, value: mixed, schema: string}>
     */
    private function debugConfigAsJson(array $input, array $values, array $schema = []): array
    {
        $tester = $this->debugConfig($input + ['--json' => true], $values, $schema);

        /** @var list<array{key: string, value: mixed, schema: string}> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param list<array{key: string, value: mixed, schema: string}> $entries
     *
     * @return array{key: string, value: mixed, schema: string}
     */
    private function entry(array $entries, string $key): array
    {
        foreach ($entries as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        self::fail(sprintf('no entry for key "%s" in the document', $key));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $values
     * @param array<string, mixed> $schema
     */
    private function debugConfig(array $input, array $values, array $schema = []): CommandTester
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($values, $schema): void {
            $config->resetInMemoryCache();
            /** @var mixed $value */
            foreach ($values as $key => $value) {
                $config->addAppConfigKeyValue($key, $value);
            }

            if ($schema !== []) {
                $config->declareConfigSchema($schema);
            }
        });

        $tester = new CommandTester(new DebugConfigCommand());
        $tester->execute($input);

        return $tester;
    }
}
