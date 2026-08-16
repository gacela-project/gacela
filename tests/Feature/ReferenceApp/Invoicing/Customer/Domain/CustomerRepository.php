<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain;

use function array_key_exists;

/**
 * In memory on purpose: the reference application demonstrates wiring, and a
 * database would only add a fixture nobody reads. A real project swaps this
 * class and changes nothing else.
 */
final class CustomerRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    private int $lookups = 0;

    public function save(CustomerProfile $profile): void
    {
        $this->rows[$profile->getReference()] = $profile->toArray();
    }

    public function find(string $reference): ?CustomerProfile
    {
        ++$this->lookups;

        if (!array_key_exists($reference, $this->rows)) {
            return null;
        }

        return CustomerProfile::fromArray($this->rows[$reference]);
    }

    /**
     * How many times the store was actually read. The facade caches its lookup,
     * so this is what proves the cache is doing something.
     */
    public function lookups(): int
    {
        return $this->lookups;
    }

    /**
     * @return list<string>
     */
    public function references(): array
    {
        return array_keys($this->rows);
    }
}
