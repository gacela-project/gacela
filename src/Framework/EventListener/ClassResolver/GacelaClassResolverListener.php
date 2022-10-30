<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener\ClassResolver;

use Gacela\Framework\EventListener\GacelaEventInterface;
use Gacela\Framework\EventListener\GacelaListenerInterface;

final class GacelaClassResolverListener implements GacelaListenerInterface
{
    public function onGacelaEvent(GacelaEventInterface $event): void
    {
    }
}
