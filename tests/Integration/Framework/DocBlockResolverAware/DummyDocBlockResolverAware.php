<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Integration\Framework\DocBlockResolverAware\Persistence\FakeRepository;
use GacelaTest\Integration\Framework\DocBlockResolverAware\Service\FakeServiceInterface;

/**
 * @method FakeRepository getRepository()
 * @method FakeServiceInterface getService()
 */
final class DummyDocBlockResolverAware
{
    use DocBlockResolverAwareTrait;
}
