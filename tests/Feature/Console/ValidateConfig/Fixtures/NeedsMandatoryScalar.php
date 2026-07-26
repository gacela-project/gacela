<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

/**
 * Type-compatible with {@see SomeContract}, so the binding itself validates,
 * but unresolvable through the container because of the mandatory scalar.
 */
final class NeedsMandatoryScalar implements SomeContract
{
    public function __construct(
        public readonly string $mandatory,
    ) {
    }
}
