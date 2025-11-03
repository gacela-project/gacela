<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolverAware;

use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Integration\Framework\ServiceResolverAware\Persistence\FakeRepository;

/**
 * @method FakeRepository getRepository()
 */
final class DummyServiceResolverAware
{
    use ServiceResolverAwareTrait;
}
