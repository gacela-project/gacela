<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractCustomService
{
    use FacadeResolverAwareTrait;
    use FactoryResolverAwareTrait;
    use ConfigResolverAwareTrait;
}
