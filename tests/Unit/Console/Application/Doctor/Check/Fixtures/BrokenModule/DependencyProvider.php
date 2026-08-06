<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\BrokenModule;

/**
 * The migration trap in its natural state: the class was renamed to `Provider`
 * and the file stayed `DependencyProvider.php`. Nothing requires this file, and
 * nothing can autoload it under PSR-4 — which is precisely why the check has to
 * find it on disk rather than through a resolved class name.
 */
final class Provider
{
}
