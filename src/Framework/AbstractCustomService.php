<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractCustomService
{
    use ConfigResolverAwareTrait;
    use FactoryResolverAwareTrait;
}
