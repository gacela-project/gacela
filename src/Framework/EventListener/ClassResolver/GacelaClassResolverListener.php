<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener\ClassResolver;

use Gacela\Framework\EventListener\GacelaEventInterface;

final class GacelaClassResolverListener
{
    public function __invoke(GacelaEventInterface $event): void
    {
    }
}
