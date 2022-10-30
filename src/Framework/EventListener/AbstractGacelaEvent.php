<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener;

use Gacela\Framework\ClassResolver\ClassInfo;

abstract class AbstractGacelaEvent implements GacelaEventInterface
{
    private ClassInfo $classInfo;

    public function __construct(ClassInfo $classInfo)
    {
        $this->classInfo = $classInfo;
    }

    public function classInfo(): ClassInfo
    {
        return $this->classInfo;
    }
}
