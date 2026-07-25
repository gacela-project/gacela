<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

/**
 * Deliberately declared in `DependencyProvider.php`: the migration from
 * `AbstractDependencyProvider` to `AbstractProvider` renames the class but
 * leaves the file behind, and Gacela resolves pillars by filename. The file
 * cannot be autoloaded under PSR-4 for exactly that reason, so the test that
 * uses it requires it by hand.
 */
final class Provider
{
}
