<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

interface GacelaConfigItemInterface
{
    public function type(): string;

    public function path(): string;

    public function pathLocal(): string;
}
