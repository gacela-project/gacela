<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class ReferenceMarker
{
}
