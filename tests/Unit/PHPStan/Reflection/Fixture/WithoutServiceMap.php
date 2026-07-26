<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\ServiceResolverAwareTrait;

final class WithoutServiceMap
{
    use ServiceResolverAwareTrait;
}
