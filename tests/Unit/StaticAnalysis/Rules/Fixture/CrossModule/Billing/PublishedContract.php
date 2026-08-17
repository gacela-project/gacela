<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing;

use Gacela\Framework\Attribute\PublicApi;

/**
 * An interface is exactly the kind of thing a module publishes -- and is not a
 * class to `class_exists()`, which is why the surface asks twice.
 */
#[PublicApi]
interface PublishedContract
{
}
