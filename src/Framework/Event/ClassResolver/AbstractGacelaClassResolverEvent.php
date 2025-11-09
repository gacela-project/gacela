<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassInfoInterface;
use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

abstract class AbstractGacelaClassResolverEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly ClassInfo $classInfo,
    ) {
    }

    public function classInfo(): ClassInfoInterface
    {
        return $this->classInfo;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {classInfo:"%s"}',
            static::class,
            $this->classInfo->toString(),
        );
    }
}
