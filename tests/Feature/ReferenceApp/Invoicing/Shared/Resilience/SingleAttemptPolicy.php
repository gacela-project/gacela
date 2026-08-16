<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience;

/**
 * The application-wide default: try once, and let the caller decide.
 */
final class SingleAttemptPolicy implements RetryPolicyInterface
{
    public function attempts(): int
    {
        return 1;
    }
}
