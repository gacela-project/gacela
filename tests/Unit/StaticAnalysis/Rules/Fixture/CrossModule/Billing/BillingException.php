<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing;

use RuntimeException;

/**
 * A module's own exception type, which a neighbour catches and reads.
 *
 * Real classes rather than strings because the exemption is `is_a()`: a name
 * comparison would say nothing about the hierarchy, and the hierarchy is the
 * thing being exempted.
 */
class BillingException extends RuntimeException
{
}
