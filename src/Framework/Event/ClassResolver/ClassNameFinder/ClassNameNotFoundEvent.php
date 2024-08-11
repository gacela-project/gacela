<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class ClassNameNotFoundEvent implements GacelaEventInterface
{
    /**
     * @param list<string> $resolvableTypes
     */
    public function __construct(
        private readonly ClassInfo $classInfo,
        private readonly array $resolvableTypes,
    ) {
    }

    public function classInfo(): ClassInfo
    {
        return $this->classInfo;
    }

    /**
     * @return list<string>
     */
    public function resolvableTypes(): array
    {
        return $this->resolvableTypes;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {classInfo:"%s", resolvableTypes:"%s"}',
            self::class,
            $this->classInfo->toString(),
            implode(',', $this->resolvableTypes),
        );
    }
}
