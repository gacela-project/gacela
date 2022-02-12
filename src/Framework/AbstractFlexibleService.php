<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractFlexibleService
{
    use ConfigResolverAwareTrait;
    use FactoryResolverAwareTrait;
}
