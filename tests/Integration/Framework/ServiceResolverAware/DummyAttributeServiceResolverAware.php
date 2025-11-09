<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolverAware;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Integration\Framework\ServiceResolverAware\Persistence\FakeRepository;

#[ServiceMap(method: 'getRepository', className: FakeRepository::class)]
final class DummyAttributeServiceResolverAware
{
    use ServiceResolverAwareTrait;
}
