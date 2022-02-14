<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractCustomService implements CustomServiceInterface
{
    use ConfigResolverAwareTrait;
    use FactoryResolverAwareTrait;
}
