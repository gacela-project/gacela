<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ProjectEvents;

use Gacela\Framework\Event\GacelaEventInterface;
use OuterIterator;
use SplFileInfo;

use function array_keys;
use function file_get_contents;
use function is_a;
use function preg_match;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * The events an application declares, as opposed to the ones Gacela ships.
 *
 * A project dispatching its own events through Gacela's dispatcher gets the
 * same guard, matching and memo the framework's events get -- and, without
 * this, none of the tooling that reports on them: `debug:events` listed only
 * `Gacela\Framework\Event\*`, so a project event was invisible to the one
 * command whose job is to answer "what is listening to what", and `doctor`
 * could not judge a listener registered against one.
 *
 * Found by walking the directories discovery already walks -- the configured
 * `appModulePaths`, or the application root -- rather than by a second scanner
 * or a scan of `vendor/`. It is the same file iterator the module finders take,
 * so a command that asks for both pays for the walks it starts, and `vendor/`
 * is pruned before it is ever reached.
 *
 * ## Which files are opened, and which classes are loaded
 *
 * Every `.php` file under those paths is read, as the module finders already
 * read them. A class is *loaded* only when the file looks like it could hold
 * an event, because loading is what costs: a whole-project `class_exists()`
 * sweep is 147ms on an application of 126 modules, and this runs inside
 * `debug:events` and `doctor`.
 *
 * Two things make a file a candidate, and either is enough:
 *
 * - it names `GacelaEventInterface`, which an event implementing it directly
 *   must do;
 * - it is named `*Event.php`, which is the convention the framework's own
 *   events follow and the one `docs/events.md` recommends.
 *
 * What that misses is an event that neither names the interface nor ends in
 * `Event` -- `class InvoiceIssued extends DomainEvent`, where the parent
 * carries the interface. The parent is found and reported (a listener target,
 * exactly as an abstract framework event is), the child is not. Naming either
 * the interface or the suffix is enough to be listed, and `debug:events` is
 * where a project sees which of its events Gacela knows about.
 */
final class ProjectEventFinder
{
    private const string EVENT_FILE_SUFFIX = 'Event.php';

    /**
     * The framework's own events are catalogued from the installed package, so
     * finding them again here would list every one of them twice in a project
     * whose scan path happens to include Gacela's source -- which is exactly
     * the shape of Gacela's own repository.
     */
    private const string FRAMEWORK_EVENT_NAMESPACE = 'Gacela\\Framework\\Event\\';

    /**
     * @param OuterIterator<array-key, SplFileInfo> $fileIterator
     * @param list<string> $projectNamespaces the `setProjectNamespaces()` list, when the
     *   application declared one: a class outside it is not the project's event
     */
    public function __construct(
        private readonly OuterIterator $fileIterator,
        private readonly array $projectNamespaces = [],
    ) {
    }

    /**
     * @return list<class-string>
     */
    public function find(): array
    {
        $classes = [];

        /** @var SplFileInfo $fileInfo */
        foreach ($this->fileIterator as $fileInfo) {
            $class = $this->eventClassIn($fileInfo);

            if ($class !== null) {
                $classes[$class] = true;
            }
        }

        $found = array_keys($classes);

        // Sorted so two runs of one project agree, whatever order the
        // filesystem hands the directories over in.
        sort($found);

        /** @var list<class-string> $found */
        return $found;
    }

    /**
     * @return class-string|null
     */
    private function eventClassIn(SplFileInfo $fileInfo): ?string
    {
        $realPath = $fileInfo->getRealPath();

        if ($realPath === false
            || !$fileInfo->isFile()
            || $fileInfo->getExtension() !== 'php'
            || str_starts_with($fileInfo->getFilename(), '.')
            || str_contains($realPath, 'vendor' . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        $fileContent = file_get_contents($realPath);

        if ($fileContent === false) {
            return null;
        }

        if (!$this->looksLikeAnEvent($fileInfo, $fileContent)) {
            return null;
        }

        $namespace = $this->namespaceOf($fileContent);

        if ($namespace === '' || !$this->isProjectNamespace($namespace)) {
            return null;
        }

        /** @var class-string $class */
        $class = $namespace . '\\' . $this->classNameOf($fileInfo);

        // The one load, and the one thing that actually decides: a file can
        // name the interface for any reason -- a listener type-hints it, a
        // service imports it -- and only the class itself says whether an event
        // is what this is.
        return is_a($class, GacelaEventInterface::class, true) ? $class : null;
    }

    private function looksLikeAnEvent(SplFileInfo $fileInfo, string $fileContent): bool
    {
        return str_ends_with($fileInfo->getFilename(), self::EVENT_FILE_SUFFIX)
            || str_contains($fileContent, 'GacelaEventInterface');
    }

    /**
     * A declared project namespace wins when there is one: it is what the
     * application already told discovery its classes are called, so a fixture
     * or a vendored path that slipped into a scan path is not mistaken for the
     * project's own event.
     *
     * With none declared, everything outside the framework's event namespace
     * counts -- `setProjectNamespaces()` is optional, and an application that
     * skipped it still has events.
     */
    private function isProjectNamespace(string $namespace): bool
    {
        if (str_starts_with($namespace . '\\', self::FRAMEWORK_EVENT_NAMESPACE)) {
            return false;
        }

        if ($this->projectNamespaces === []) {
            return true;
        }

        foreach ($this->projectNamespaces as $projectNamespace) {
            if (str_starts_with($namespace . '\\', $projectNamespace . '\\')) {
                return true;
            }
        }

        return false;
    }

    private function namespaceOf(string $fileContent): string
    {
        preg_match('#namespace (.*);#', $fileContent, $matches);

        return $matches[1] ?? '';
    }

    private function classNameOf(SplFileInfo $fileInfo): string
    {
        $filename = $fileInfo->getFilename();
        $dotPosition = strpos($filename, '.');

        return $dotPosition !== false ? substr($filename, 0, $dotPosition) : $filename;
    }
}
