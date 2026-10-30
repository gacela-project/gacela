<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class ReferenceMarker
{
}
