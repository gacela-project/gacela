<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules\Fixtures\NotLoaded;

/**
 * Autoloadable, and referenced by exactly one test -- by name, as a string,
 * never as a symbol.
 *
 * That is the whole fixture. The finder skips a file where no class extends
 * anything, and the only way to observe the skip is a class that *would* load
 * if it were asked for: whether it ends up declared is the difference between
 * the guard being there and not, where the module list is identical either way.
 */
final class ExtendsNothing
{
}
