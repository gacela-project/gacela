<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\SharedFoo;

/**
 * `Shared` is a raw-string prefix of `SharedFoo` but not a namespace boundary,
 * so the exemption must not reach this one.
 */
final class Thing
{
    public function run(): string
    {
        return 'thing';
    }
}
