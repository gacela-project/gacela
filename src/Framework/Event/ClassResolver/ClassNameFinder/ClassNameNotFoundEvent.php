<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Event\GacelaEventInterface;

final class ClassNameNotFoundEvent implements GacelaEventInterface
{
    /**
     * @param list<string> $resolvableTypes
     */
    public function __construct(
        private ClassInfo $classInfo,
        private array $resolvableTypes,
    ) {
    }

    public function toString(): string
    {
        return sprintf(
            '%s - %s - %s',
            self::class,
            $this->classInfo->toString(),
            implode(',', $this->resolvableTypes),
        );
    }
}
