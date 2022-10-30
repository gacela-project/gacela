<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener\ConfigReader;

use Gacela\Framework\EventListener\GacelaEventInterface;

final class GacelaConfigReaderListener
{
    public function __invoke(GacelaEventInterface $event): void
    {
    }
}
