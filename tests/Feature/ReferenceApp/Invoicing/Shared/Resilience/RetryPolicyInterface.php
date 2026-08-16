<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience;

interface RetryPolicyInterface
{
    /**
     * How many times an operation may be attempted in total, the first try
     * included.
     */
    public function attempts(): int;
}
