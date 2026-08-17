<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PublicApiFixture\Billing\Domain;

/**
 * Published by nothing, which is the precondition for every silent assertion in
 * these tests: the check really is running over this fixture set.
 */
final class InvoiceRepository
{
    /**
     * @return list<string>
     */
    public function findAll(): array
    {
        return [];
    }
}
