<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ClassResolver;

use Gacela\Framework\Event\GacelaEventInterface;
use Override;

use function sprintf;

final class GenericEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $text,
    ) {
    }

    #[Override]
    public function toString(): string
    {
        return sprintf('GenericEvent: %s', $this->text);
    }
}
