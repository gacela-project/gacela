<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing;

use Gacela\Framework\Attribute\PublicApi;

/**
 * The attribute on a trait, which `TARGET_CLASS` permits and nothing reads.
 *
 * Nothing can name a trait across a boundary -- it is `use`d into a class rather
 * than instantiated, named statically or called on -- so an export here would be
 * a promise no rule keeps. Pinned so that stays a decision rather than an
 * oversight.
 */
#[PublicApi]
trait PublishedBehaviour
{
}
