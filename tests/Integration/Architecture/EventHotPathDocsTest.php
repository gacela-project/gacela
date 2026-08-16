<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Console\Application\Debug\EventCatalog;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_pop;
use function count;
use function explode;
use function file_get_contents;
use function preg_match;
use function sort;
use function str_starts_with;
use function strrpos;
use function substr;
use function trim;

/**
 * `debug:events` marks the events dispatched on every warm resolve, and
 * `docs/events.md` marks the same ones in a "Hot path" column. Two lists of the
 * same fact, written in two places, neither reading the other.
 *
 * The column is not parsed to drive the command -- a markdown table read by
 * people should not quietly become load-bearing, and the failure mode of
 * parsing it is a command that reports whatever a typo left behind. So the code
 * carries the list, and this compares them.
 */
final class EventHotPathDocsTest extends TestCase
{
    private const string EVENTS_DOCS = __DIR__ . '/../../../docs/events.md';

    public function test_the_command_marks_the_events_the_docs_mark(): void
    {
        $documented = $this->documentedHotPathEvents();
        $marked = array_map(
            $this->shortNameOf(...),
            EventCatalog::hotPathEvents(),
        );

        sort($documented);
        sort($marked);

        self::assertSame($documented, $marked, <<<'MESSAGE'
            The "Hot path" column of docs/events.md and EventCatalog::hotPathEvents()
            disagree. Whichever is right, both have to say it: the docs are what a
            reader trusts, and the list is what `debug:events` prints.
            MESSAGE);
    }

    public function test_the_docs_mark_something_at_all(): void
    {
        self::assertGreaterThan(
            0,
            count($this->documentedHotPathEvents()),
            'no "**yes**" row found in docs/events.md -- the parsing, not the docs, is wrong',
        );
    }

    /**
     * Every catalog row whose last column says `**yes**`. The tables do not all
     * have the same number of columns -- the resolver one carries no payload --
     * so "hot path" is read as the last cell rather than the fourth.
     *
     * @return list<string>
     */
    private function documentedHotPathEvents(): array
    {
        $events = [];

        foreach (explode("\n", (string)file_get_contents(self::EVENTS_DOCS)) as $line) {
            if (!str_starts_with($line, '| `')) {
                continue;
            }

            $cells = array_map(trim(...), explode('|', trim($line, '|')));

            if (preg_match('/^`([A-Za-z\\\\]+Event)`$/', $cells[0], $match) !== 1) {
                continue;
            }

            if (array_pop($cells) !== '**yes**') {
                continue;
            }

            $events[] = $this->shortNameOf($match[1]);
        }

        return $events;
    }

    private function shortNameOf(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}
