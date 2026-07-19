<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Config;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

final class ConfigKeyReadEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $key,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {key:"%s"}',
            self::class,
            $this->key,
        );
    }
}
