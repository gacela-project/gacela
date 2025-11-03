<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolverAware;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @method NonExistingResolverAware getRepository()
 */
final class BadDummyServiceResolverAware
{
    use ServiceResolverAwareTrait;
}
