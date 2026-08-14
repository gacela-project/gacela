<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

/**
 * A file named like a Facade that produced no module.
 *
 * Discovery accepts a module by asking whether the class descends from
 * `AbstractFacade`, and both ways of failing that question are silent: a class
 * PHP cannot load is skipped, and one that loads but extends something else is
 * skipped too. Either way the file sits on disk looking like a module, and the
 * only symptom is a module missing from `list:modules`, `doctor`, `debug:graph`
 * and `cache:warm` alike.
 */
final class UndiscoveredFacadeFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $className,
        public readonly UndiscoveredFacadeProblem $problem,
    ) {
    }
}
