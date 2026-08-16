<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain;

use Closure;

/**
 * Hands out invoice numbers, one sequence per process.
 *
 * The starting number is set by an `afterResolving()` hook rather than by a
 * constructor argument, because the deployment decides where a fresh ledger
 * begins. Setting a property is safe there: the hook runs once per resolution,
 * not once per instance, so anything that accumulated would repeat.
 *
 * The format is a callback the application registers with `addProtected()` --
 * a closure the container hands back instead of calling, which is the whole
 * point of registering it that way.
 */
final class InvoiceNumbering
{
    private int $sequence = 0;

    /**
     * @param Closure(string, int):string $format
     */
    public function __construct(
        private readonly Closure $format,
    ) {
    }

    public function startFrom(int $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function next(string $prefix): string
    {
        ++$this->sequence;

        return ($this->format)($prefix, $this->sequence);
    }
}
