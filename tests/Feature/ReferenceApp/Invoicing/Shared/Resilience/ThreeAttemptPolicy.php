<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience;

/**
 * What the payment module gets instead, through a contextual binding: a
 * capture that fails on a timeout is worth retrying, a notification is not.
 */
final class ThreeAttemptPolicy implements RetryPolicyInterface
{
    public function attempts(): int
    {
        return 3;
    }
}
