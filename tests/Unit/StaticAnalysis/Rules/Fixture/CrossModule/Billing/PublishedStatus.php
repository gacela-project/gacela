<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing;

use Gacela\Framework\Attribute\PublicApi;

/**
 * `TARGET_CLASS` covers an enum as well, which is the case the attribute exists
 * for as much as the DTO one.
 */
#[PublicApi]
enum PublishedStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
}
