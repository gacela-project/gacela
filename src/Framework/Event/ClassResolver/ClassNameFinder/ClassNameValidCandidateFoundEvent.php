<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\Event\GacelaEventInterface;

final class ClassNameValidCandidateFoundEvent implements GacelaEventInterface
{
    private string $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function toString(): string
    {
        return sprintf('%s - %s', self::class, $this->className);
    }
}
