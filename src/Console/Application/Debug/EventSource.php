<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

/**
 * Who declared an event: the framework, or the application reading the report.
 *
 * Worth a column because the two answer different questions. A framework event
 * is documented in `docs/events.md` and its payload is fixed; a project event
 * is the reader's own, and seeing it listed is how they know Gacela found it --
 * which is the difference between "no listener is registered" and "this class
 * is not where discovery looks".
 */
enum EventSource: string
{
    case Framework = 'framework';

    case Project = 'project';

    /**
     * Framework first in every report.
     *
     * Given as a number rather than left to the string values: `framework`
     * sorting before `project` is an accident of the alphabet, and renaming a
     * case would silently reorder every listing.
     */
    public function order(): int
    {
        return $this === self::Framework ? 0 : 1;
    }
}
