<?php

declare(strict_types=1);

namespace Gacela\Framework\EventListener\ClassResolver;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassInfoInterface;
use Gacela\Framework\EventListener\GacelaEventInterface;

use function get_class;

abstract class AbstractGacelaClassResolverEvent implements GacelaEventInterface
{
    private ClassInfo $classInfo;

    public function __construct(ClassInfo $classInfo)
    {
        $this->classInfo = $classInfo;
    }

    public function classInfo(): ClassInfoInterface
    {
        return $this->classInfo;
    }

    public function toString(): string
    {
        return sprintf(
            '%s - %s',
            get_class($this),
            $this->classInfo->toString()
        );
    }
}
