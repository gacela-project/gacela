<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm\Fixture;

/**
 * An interface is as resolvable as a class, and is the more common thing for a
 * Provider to hand out.
 */
interface ProvidedContract
{
    public function run(): void;
}
