<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

/**
 * Why a Facade-named file produced no module. The two have different fixes,
 * which is the whole reason to tell them apart rather than report "not found".
 */
enum UndiscoveredFacadeProblem: string
{
    /**
     * PHP cannot load the class the filename implies -- an autoload prefix that
     * does not cover the directory, a `namespace` declaration that disagrees
     * with the path, or a classmap that was never dumped again.
     */
    case NotLoadable = 'not-loadable';

    /**
     * The class loads and is named like a Facade, but does not descend from
     * `AbstractFacade`. Usually a missing `extends` on a new module.
     */
    case NotAFacade = 'not-a-facade';
}
