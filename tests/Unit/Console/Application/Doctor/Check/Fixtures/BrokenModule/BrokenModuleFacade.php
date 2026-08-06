<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\BrokenModule;

/**
 * Resolves normally. It is what makes the module discoverable at all, and what
 * the check uses to locate the directory its siblings live in.
 */
final class BrokenModuleFacade
{
}
