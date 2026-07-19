<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Container;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class BindingRegisteredEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $id,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {id:"%s"}',
            self::class,
            $this->id,
        );
    }
}
