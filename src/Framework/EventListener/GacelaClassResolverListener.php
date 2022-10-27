<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener;

use Gacela\Framework\EventListener\Event\GacelaEventInterface;

final class GacelaClassResolverListener
{
    public function __invoke(GacelaEventInterface $event): void
    {
    }
}
