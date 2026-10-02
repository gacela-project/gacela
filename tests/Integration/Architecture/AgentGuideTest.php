<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Console\Infrastructure\Command\CommandCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

use function array_diff;
use function array_map;
use function array_unique;
use function array_values;
use function file_get_contents;
use function implode;
use function preg_match_all;
use function sort;
use function sprintf;

/**
 * The guide `agents:install` points a project's AGENTS.md at. An agent trusts
 * it more than a person would, so a command it names that does not exist, or
 * one it never mentions, is a wrong answer it will act on.
 */
final class AgentGuideTest extends TestCase
{
    private const string GUIDE = __DIR__ . '/../../../resources/agents/gacela.md';

    public function test_the_command_table_lists_every_command_and_nothing_else(): void
    {
        preg_match_all('/^\| `([a-z:-]+)` \|/m', (string) file_get_contents(self::GUIDE), $matches);
        $listed = $matches[1];
        $shipped = $this->shippedCommands();

        self::assertSame([], array_values(array_diff($shipped, $listed)), sprintf(
            'The agent guide\'s "All commands" table misses: %s. Add a row.',
            implode(', ', array_diff($shipped, $listed)),
        ));
        self::assertSame([], array_values(array_diff($listed, $shipped)), sprintf(
            'The agent guide\'s "All commands" table lists commands that do not exist: %s.',
            implode(', ', array_diff($listed, $shipped)),
        ));
    }

    public function test_every_command_the_guide_runs_exists(): void
    {
        preg_match_all('/vendor\/bin\/gacela ([a-z][a-z:-]*)/', (string) file_get_contents(self::GUIDE), $matches);
        $run = array_values(array_unique($matches[1]));

        self::assertNotSame([], $run);
        self::assertSame([], array_values(array_diff($run, $this->shippedCommands())));
    }

    /**
     * @return list<string>
     */
    private function shippedCommands(): array
    {
        $names = array_map(
            static fn (Command $command): string => (string) $command->getName(),
            CommandCatalog::instances(''),
        );
        sort($names);

        return $names;
    }
}
