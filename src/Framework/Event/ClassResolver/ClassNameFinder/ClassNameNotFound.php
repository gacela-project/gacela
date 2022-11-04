<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Event\GacelaEventInterface;

final class ClassNameNotFound implements GacelaEventInterface
{
    private ClassInfo $classInfo;

    /** @var list<string> */
    private array $resolvableTypes;

    /**
     * @param list<string> $resolvableTypes
     */
    public function __construct(ClassInfo $classInfo, array $resolvableTypes)
    {
        $this->classInfo = $classInfo;
        $this->resolvableTypes = $resolvableTypes;
    }

    public function toString(): string
    {
        return sprintf(
            '%s - %s - %s',
            self::class,
            $this->classInfo->toString(),
            implode(',', $this->resolvableTypes)
        );
    }
}
