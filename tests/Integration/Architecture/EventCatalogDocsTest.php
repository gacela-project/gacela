<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use FilesystemIterator;
use Gacela\Console\Application\Debug\EventCatalog;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function array_diff;
use function array_map;
use function array_values;
use function class_exists;
use function count;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function preg_match;
use function realpath;
use function sort;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * `docs/events.md` lists the event classes by hand, in tables, and nothing fails
 * when the next one is added undocumented. Two were added in #867 and had to be
 * remembered; the page is the only inventory a reader has, so an event missing
 * from it is an event nobody knows they can listen to.
 *
 * The files on disk are the truth and the page is checked against them --
 * walked here rather than read off `EventCatalog`, because a catalog that had
 * itself stopped finding an event would agree with a page that never mentioned
 * it. The last test closes that loop from the other side.
 *
 * The "Hot path" column is not this test's business: {@see EventHotPathDocsTest}
 * owns it.
 */
final class EventCatalogDocsTest extends TestCase
{
    private const string EVENTS_DOCS = __DIR__ . '/../../../docs/events.md';

    private const string EVENT_DIR = __DIR__ . '/../../../src/Framework/Event';

    private const string EVENT_NAMESPACE = 'Gacela\\Framework\\Event\\';

    /**
     * An abstract event is a listener target rather than something dispatched,
     * so it earns prose and not a catalog row -- but it still has to be on the
     * page, because registering against it is the documented way to cover a
     * family. The test below holds it to that.
     */
    public function test_every_dispatched_event_has_a_row_in_the_catalog(): void
    {
        $documented = $this->documentedEventNames();
        $missing = [];

        foreach ($this->eventClassesOnDisk() as $class) {
            if ($this->isAbstract($class)) {
                continue;
            }

            if (!in_array($this->shortNameOf($class), $documented, true)) {
                $missing[] = $class;
            }
        }

        self::assertSame([], $missing, sprintf(
            "These events are dispatched and sit in no docs/events.md table: %s.\n"
            . 'The page is the only inventory a reader has -- an event missing from it is one nobody knows they can listen to.',
            implode(', ', $missing),
        ));
    }

    public function test_every_listener_target_that_is_not_dispatched_is_still_named(): void
    {
        $page = (string)file_get_contents(self::EVENTS_DOCS);
        $abstract = [];
        $missing = [];

        foreach ($this->eventClassesOnDisk() as $class) {
            if (!$this->isAbstract($class)) {
                continue;
            }

            $abstract[] = $class;

            if (!str_contains($page, $this->shortNameOf($class))) {
                $missing[] = $class;
            }
        }

        // Without one, everything below passes by looking at nothing.
        self::assertNotSame([], $abstract, 'no abstract event found -- this test is now checking nothing');

        self::assertSame([], $missing, sprintf(
            'These abstract events appear on the page nowhere: %s. Registering against one is the documented way to cover a family.',
            implode(', ', $missing),
        ));
    }

    /**
     * The other direction, which is the one that rots quietly: a row survives
     * the class being renamed or removed, and reads as a promise the framework
     * no longer keeps.
     */
    public function test_every_event_named_in_the_catalog_exists(): void
    {
        $onDisk = array_map(
            $this->shortNameOf(...),
            $this->eventClassesOnDisk(),
        );

        $unknown = array_values(array_diff($this->documentedEventNames(), $onDisk));

        self::assertSame([], $unknown, sprintf(
            'docs/events.md documents %s, and no such event class exists under src/Framework/Event.',
            implode(', ', $unknown),
        ));
    }

    /**
     * Nothing above means anything if the parsing quietly stopped matching.
     */
    public function test_both_sides_of_the_comparison_are_populated(): void
    {
        self::assertGreaterThan(20, count($this->eventClassesOnDisk()));
        self::assertGreaterThan(20, count($this->documentedEventNames()));
    }

    /**
     * `debug:events` prints what `EventCatalog` finds, and it finds classes
     * rather than files: a `*Event.php` whose class does not implement
     * `GacelaEventInterface`, or is not named after its path, is skipped without
     * a word. The command would then report a catalog agreeing with neither the
     * page nor the directory.
     */
    public function test_the_shipped_catalog_finds_every_event_on_disk(): void
    {
        $found = (new EventCatalog())->eventClasses();
        $onDisk = $this->eventClassesOnDisk();

        sort($found);

        self::assertSame($onDisk, $found, sprintf(
            'EventCatalog misses %s.',
            implode(', ', array_diff($onDisk, $found)),
        ));
    }

    /**
     * Every `*Event.php` under the event directory, as class names.
     *
     * @return list<string>
     */
    private function eventClassesOnDisk(): array
    {
        $root = (string)realpath(self::EVENT_DIR);
        $classes = [];

        /** @var iterable<SplFileInfo> $entries */
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($entries as $entry) {
            if (!str_ends_with($entry->getFilename(), 'Event.php')) {
                continue;
            }

            $relative = substr($entry->getPathname(), strlen($root) + 1, -strlen('.php'));
            $classes[] = self::EVENT_NAMESPACE . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }

        sort($classes);

        return $classes;
    }

    /**
     * The first cell of every catalog row, as a short name. The tables write
     * some events with the namespace they sit in (`ConfigReader\ReadPhpConfigEvent`)
     * and some without, which is a reader's convenience rather than a fact worth
     * asserting.
     *
     * @return list<string>
     */
    private function documentedEventNames(): array
    {
        $names = [];

        foreach (explode("\n", (string)file_get_contents(self::EVENTS_DOCS)) as $line) {
            if (!str_starts_with($line, '| `')) {
                continue;
            }

            $cells = explode('|', trim($line, '|'));

            if (preg_match('/^`([A-Za-z\\\\]+Event)`$/', trim($cells[0]), $match) !== 1) {
                continue;
            }

            $names[] = $this->shortNameOf($match[1]);
        }

        sort($names);

        return $names;
    }

    private function isAbstract(string $class): bool
    {
        return class_exists($class) && (new ReflectionClass($class))->isAbstract();
    }

    private function shortNameOf(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}
