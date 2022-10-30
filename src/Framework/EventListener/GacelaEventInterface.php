<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener;

use Gacela\Framework\ClassResolver\ClassInfoInterface;

interface GacelaEventInterface
{
    public function classInfo(): ClassInfoInterface;
}
