<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractFacade
{
    use FactoryResolverAwareTrait;
    use CustomServiceAwareTrait;
}
