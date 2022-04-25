<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Integration\Framework\DocBlockResolverAware\Persistence\FakeRepository;

/**
 * @method FakeRepository getRepository()
 */
final class DummyDocBlockResolverAware
{
    use DocBlockResolverAwareTrait;
}
