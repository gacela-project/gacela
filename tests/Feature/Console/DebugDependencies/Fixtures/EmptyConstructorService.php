<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

/**
 * Has a constructor that takes nothing, which the report distinguishes from
 * having no constructor at all.
 */
final class EmptyConstructorService
{
    public function __construct()
    {
    }
}
