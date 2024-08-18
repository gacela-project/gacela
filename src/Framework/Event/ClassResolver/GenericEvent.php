<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver;

use Gacela\Framework\Event\GacelaEventInterface;

final class GenericEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $text,
    ) {
    }

    public function toString(): string
    {
        return sprintf('GenericEvent: %s', $this->text);
    }
}
