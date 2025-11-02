<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use Gacela\Framework\DocBlockResolver\Doc;
use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Integration\Framework\DocBlockResolverAware\Persistence\FakeRepository;

#[Doc(method: 'getRepository', className: FakeRepository::class)]
final class DummyAttributeDocBlockResolverAware
{
    use DocBlockResolverAwareTrait;
}
