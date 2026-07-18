<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugDependencies\Fixtures;

/**
 * Exercises the three otherwise-uncovered ConstructorInspector branches:
 * an untyped parameter without a default (NoTypeHint), a union-typed
 * parameter (UnsupportedType), and a parameter typed to a non-existent
 * class (MissingType).
 */
final class UntypedAndUnionService
{
    /**
     * @param mixed $untyped
     */
    public function __construct(
        $untyped,
        int|string $union,
        \Totally\Missing\Type $missing,
    ) {
    }
}
