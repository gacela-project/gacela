<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Console\Infrastructure\Command\CommandCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputOption;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function basename;
use function file;
use function glob;
use function implode;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function sprintf;

/**
 * Holds the flags the docs name against the command definitions, so a renamed,
 * added or removed option fails here until the page follows it.
 */
final class ConsoleDocsTest extends TestCase
{
    private const CLI_DOCS = __DIR__ . '/../../../docs/cli.md';

    private const DOCS_GLOB = __DIR__ . '/../../../docs/*.md';

    public function test_every_command_has_a_row_in_cli_docs(): void
    {
        $rows = $this->cliRows();

        foreach (array_keys($this->options()) as $name) {
            self::assertArrayHasKey($name, $rows, sprintf('`%s` has no row in docs/cli.md', $name));
        }
    }

    public function test_every_option_is_named_in_its_command_row(): void
    {
        $rows = $this->cliRows();

        foreach ($this->options() as $name => $options) {
            $documented = $this->flagsIn($rows[$name] ?? '');
            foreach ($options as $option) {
                self::assertContains(
                    $option,
                    $documented,
                    sprintf('`%s --%s` exists but its row in docs/cli.md does not name it', $name, $option),
                );
            }
        }
    }

    public function test_a_command_row_names_no_option_the_command_lacks(): void
    {
        $options = $this->options();

        foreach ($this->cliRows() as $name => $row) {
            foreach ($this->flagsIn($row) as $flag) {
                self::assertContains(
                    $flag,
                    $options[$name],
                    sprintf('docs/cli.md documents `%s --%s`, which the command does not define', $name, $flag),
                );
            }
        }
    }

    public function test_every_invocation_in_the_docs_uses_real_options(): void
    {
        $options = $this->options();
        $names = $this->namesPattern();

        foreach ($this->docLines() as $where => $line) {
            preg_match_all('/(?:gacela |`)(' . $names . ')((?: [^`#|]*)?)/', $line, $matches, PREG_SET_ORDER);
            foreach ($matches as [, $name, $arguments]) {
                foreach ($this->flagsIn($arguments) as $flag) {
                    self::assertContains(
                        $flag,
                        $options[$name],
                        sprintf('%s runs `%s --%s`, which the command does not define', $where, $name, $flag),
                    );
                }
            }
        }
    }

    /**
     * A sentence saying a run exits non-zero names the command and the flag that
     * makes it so; both have to still exist for the claim to mean anything.
     */
    public function test_every_exit_code_claim_names_a_real_command_and_flag(): void
    {
        $options = $this->options();
        $names = $this->namesPattern();

        foreach ($this->docLines() as $where => $line) {
            if (preg_match('/exits? non-zero|exit with a failure/i', $line) !== 1) {
                continue;
            }

            if (preg_match('/(?:gacela |`)(' . $names . ')[ `]/', $line, $command) !== 1) {
                continue;
            }

            foreach ($this->flagsIn($line) as $flag) {
                self::assertContains(
                    $flag,
                    $options[$command[1]],
                    sprintf('%s says `%s --%s` changes the exit code, but the command has no such option', $where, $command[1], $flag),
                );
            }
        }
    }

    public function test_json_is_shorthand_wherever_a_command_takes_a_format(): void
    {
        foreach ($this->options() as $name => $options) {
            if (in_array('format', $options, true)) {
                self::assertContains('json', $options, sprintf('docs/cli.md says `--json` is shorthand for `--format=json`, but `%s` has no `--json`', $name));
            }
        }
    }

    /**
     * @return iterable<string, string> `page.md:line` => line
     */
    private function docLines(): iterable
    {
        foreach ((array)glob(self::DOCS_GLOB) as $page) {
            foreach ((array)file((string)$page) as $number => $line) {
                yield sprintf('docs/%s:%d', basename((string)$page), $number + 1) => (string)$line;
            }
        }
    }

    private function namesPattern(): string
    {
        return implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            array_keys($this->options()),
        ));
    }

    /**
     * @return array<string, list<string>> command name => option names
     */
    private function options(): array
    {
        $options = [];
        foreach (CommandCatalog::instances(__DIR__) as $command) {
            $options[(string)$command->getName()] = array_values(array_map(
                static fn (InputOption $option): string => $option->getName(),
                $command->getDefinition()->getOptions(),
            ));
        }

        return $options;
    }

    /**
     * Read out of the table rows, which look like:
     * `| `debug:graph` | The module dependency graph. `--check` to fail on cycles |`
     *
     * @return array<string, string> command name => the rest of its row
     */
    private function cliRows(): array
    {
        $commands = $this->options();
        $rows = [];

        foreach ((array)file(self::CLI_DOCS) as $line) {
            if (preg_match('/^\| `([a-z:-]+)[^`]*` \|(.*)$/', (string)$line, $match) !== 1) {
                continue;
            }

            if (isset($commands[$match[1]])) {
                $rows[$match[1]] = $match[2];
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function flagsIn(string $text): array
    {
        preg_match_all('/(?<![\w-])--([a-z][a-z-]*)/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }
}
