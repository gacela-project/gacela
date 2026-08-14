<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * A bare `#[Inject]` on an interface nothing binds.
 *
 * The attribute names no implementation, so the container resolves the
 * parameter's own type -- and cannot. `--check` used to pass this, because the
 * attribute short-circuited the inspection and read as proof that something
 * could supply it.
 */
final class InjectUnboundService
{
    public function __construct(
        #[Inject] public readonly UnboundContract $unbound,
    ) {
    }
}
