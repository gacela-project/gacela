<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use FilesystemIterator;
use Gacela\Framework\Event\GacelaEventInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function class_exists;
use function dirname;
use function in_array;
use function is_a;
use function sort;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;
use function usort;

use const DIRECTORY_SEPARATOR;

/**
 * Every event Gacela can dispatch, and what listens to it.
 *
 * Read off the directory rather than listed here: a catalog restating the
 * classes is a second place to forget, which is the fault `docs/events.md`
 * already has -- two events were added in #867 and had to be remembered.
 *
 * {@see eventClasses()} stays the framework's own, and nothing else: it is what
 * `EventCatalogDocsTest` compares `docs/events.md` against in both directions,
 * and a project's events appearing in it would make that page's tables either
 * wrong or impossible to satisfy. An application's events are found by
 * {@see \Gacela\Console\Domain\ProjectEvents\ProjectEventFinder} and handed to
 * {@see inspect()}, which marks each row with the {@see EventSource} it came
 * from.
 */
final class EventCatalog
{
    private const string EVENT_NAMESPACE = 'Gacela\\Framework\\Event\\';

    private const string EVENT_FILE_SUFFIX = 'Event.php';

    /**
     * The events dispatched on every warm resolve, which is what makes a
     * listener on one worth knowing about: it runs on a path measured in
     * hundreds of nanoseconds.
     *
     * Written out rather than derived. `docs/events.md` marks the same eight in
     * a "Hot path" column, but that is a markdown table read by people, and
     * parsing prose to drive behaviour makes the prose load-bearing in a way
     * nobody editing it would expect. `EventHotPathDocsTest` compares the two
     * instead, so the list and the column cannot drift apart quietly.
     *
     * @var list<string>
     */
    private const array HOT_PATH_EVENTS = [
        self::EVENT_NAMESPACE . 'Attribute\\CacheableHitEvent',
        self::EVENT_NAMESPACE . 'Attribute\\CacheableMissEvent',
        self::EVENT_NAMESPACE . 'ClassResolver\\Cache\\ClassNameCacheCachedEvent',
        self::EVENT_NAMESPACE . 'ClassResolver\\Cache\\CustomServicesCacheCachedEvent',
        self::EVENT_NAMESPACE . 'ClassResolver\\ClassNameFinder\\ClassNameCachedFoundEvent',
        self::EVENT_NAMESPACE . 'ClassResolver\\ResolvedClassCachedEvent',
        self::EVENT_NAMESPACE . 'Config\\ConfigKeyReadEvent',
        self::EVENT_NAMESPACE . 'Container\\ServiceResolvedEvent',
    ];

    /**
     * @return list<string>
     */
    public static function hotPathEvents(): array
    {
        return self::HOT_PATH_EVENTS;
    }

    /**
     * Every `*Event.php` under the framework's event directory, found from the
     * installed package rather than a path relative to a project, so this works
     * the same from `vendor/bin/gacela` as it does from this repository.
     *
     * @return list<class-string>
     */
    public function eventClasses(): array
    {
        $root = $this->eventDirectory();
        $classes = [];

        foreach ($this->entriesUnder($root) as $file) {
            if (!str_ends_with($file->getFilename(), self::EVENT_FILE_SUFFIX)) {
                continue;
            }

            $class = $this->classNameOf($root, $file->getPathname());

            if (!is_a($class, GacelaEventInterface::class, true)) {
                continue;
            }

            $classes[] = $class;
        }

        // Sorted so two runs of one project agree, and so a namespace's events
        // come out together.
        sort($classes);

        return $classes;
    }

    /**
     * @param list<class-string>  $eventClasses           what {@see eventClasses()} found
     * @param array<class-string, int> $specificListenerCounts registered target => how many listeners it carries
     * @param int                 $genericListenerCount   registerGenericListener() callables
     * @param list<class-string>  $projectEventClasses    what the application declared, from
     *   {@see \Gacela\Console\Domain\ProjectEvents\ProjectEventFinder}
     *
     * @return list<EventInspection>
     */
    public function inspect(
        array $eventClasses,
        array $specificListenerCounts,
        int $genericListenerCount,
        array $projectEventClasses = [],
    ): array {
        $inspections = [];

        foreach ($eventClasses as $eventClass) {
            $inspections[] = $this->inspectOne(
                $eventClass,
                EventSource::Framework,
                $specificListenerCounts,
                $genericListenerCount,
            );
        }

        foreach ($projectEventClasses as $eventClass) {
            $inspections[] = $this->inspectOne(
                $eventClass,
                EventSource::Project,
                $specificListenerCounts,
                $genericListenerCount,
            );
        }

        // Source first, so the framework's catalog and the project's own events
        // are two runs rather than one interleaved list -- a reader scanning for
        // "my events" should not have to read the whole table. Then by group, so
        // a namespace is contiguous: sorting the class names instead splits
        // `ClassResolver` around its own subdirectories, with `ClassResolver\Cache`
        // between the abstract parent and the four `ResolvedClass*` events.
        usort(
            $inspections,
            static fn (EventInspection $a, EventInspection $b): int => [$a->source->order(), $a->group, $a->shortName()]
                <=> [$b->source->order(), $b->group, $b->shortName()],
        );

        return $inspections;
    }

    /**
     * @param class-string $eventClass
     * @param array<class-string, int> $specificListenerCounts
     */
    private function inspectOne(
        string $eventClass,
        EventSource $source,
        array $specificListenerCounts,
        int $genericListenerCount,
    ): EventInspection {
        return new EventInspection(
            $eventClass,
            $this->groupOf($eventClass, $source),
            $this->isAbstract($eventClass),
            in_array($eventClass, self::HOT_PATH_EVENTS, true),
            $this->targetsCovering($eventClass, $specificListenerCounts),
            $genericListenerCount,
            $source,
        );
    }

    /**
     * The dispatcher's own rule, asked of a class name: a listener covers an
     * event when the event is that class or descends from it. `is_a()` with
     * `allow_string` answers without an instance, which is the only thing there
     * is here -- nothing is being dispatched.
     *
     * @param array<class-string, int> $specificListenerCounts
     *
     * @return array<class-string, int>
     */
    private function targetsCovering(string $eventClass, array $specificListenerCounts): array
    {
        $covering = [];

        foreach ($specificListenerCounts as $target => $count) {
            if (is_a($eventClass, $target, true)) {
                $covering[$target] = $count;
            }
        }

        return $covering;
    }

    /**
     * The namespace below `Gacela\Framework\Event\`, empty for an event sitting
     * directly in it.
     *
     * A project's event is grouped by its own namespace instead, written out in
     * full: there is no root to make it relative to, and the namespace is
     * exactly what tells a reader which module the event belongs to.
     */
    private function groupOf(string $eventClass, EventSource $source): string
    {
        $relative = $source === EventSource::Framework
            ? substr($eventClass, strlen(self::EVENT_NAMESPACE))
            : $eventClass;

        $position = strrpos($relative, '\\');

        return $position === false ? '' : substr($relative, 0, $position);
    }

    private function isAbstract(string $eventClass): bool
    {
        return class_exists($eventClass) && (new ReflectionClass($eventClass))->isAbstract();
    }

    private function eventDirectory(): string
    {
        $file = (new ReflectionClass(GacelaEventInterface::class))->getFileName();

        // Reflection reports false only for an internal class, and this one is
        // ours; the cast keeps the types honest without a branch nothing takes.
        return dirname((string)$file);
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function entriesUnder(string $root): iterable
    {
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        return $files;
    }

    /**
     * @return class-string
     */
    private function classNameOf(string $root, string $path): string
    {
        $relative = substr($path, strlen($root) + 1, -strlen('.php'));

        /** @var class-string $class */
        $class = self::EVENT_NAMESPACE . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return $class;
    }
}
