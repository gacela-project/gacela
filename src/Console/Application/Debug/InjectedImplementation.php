<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

/**
 * What an `#[Inject]` says about a parameter.
 *
 * `$exists` carries three answers, not two: the attribute names a class that is
 * there, names one that is not, or names none at all -- and that last case is
 * not a failure, it means the container resolves the parameter's own type and
 * this says nothing about whether it can.
 *
 * @internal
 */
final class InjectedImplementation
{
    /**
     * @param ?bool $exists true when the named class is there, false when it is
     *                      not, null when the attribute names none
     */
    public function __construct(
        public readonly string $detail,
        public readonly ?bool $exists,
    ) {
    }
}
