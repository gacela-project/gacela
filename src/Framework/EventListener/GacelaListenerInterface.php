<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener;

interface GacelaListenerInterface
{
    public function onGacelaEvent(GacelaEventInterface $event): void;
}
