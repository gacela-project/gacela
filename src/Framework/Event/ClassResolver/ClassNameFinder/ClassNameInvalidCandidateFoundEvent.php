<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver\ClassNameFinder;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class ClassNameInvalidCandidateFoundEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $className,
    ) {
    }

    public function className(): string
    {
        return $this->className;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {className:"%s"}',
            self::class,
            $this->className,
        );
    }
}
