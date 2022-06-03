<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use Gacela\Framework\DocBlockResolverAwareTrait;

/**
 * @method NonExistingResolverAware getRepository()
 */
final class BadDummyDocBlockResolverAware
{
    use DocBlockResolverAwareTrait;
}
