<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing;

use Gacela\Framework\Attribute\PublicApi;

/**
 * The module saying, at the source, that this one crosses the boundary.
 */
#[PublicApi]
class PublishedInvoice
{
}
