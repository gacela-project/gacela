<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\ConfigReader;

use Gacela\Framework\Event\GacelaEventInterface;

use function get_class;

final class ReadPhpConfigEvent implements GacelaEventInterface
{
    private string $absolutePath;

    public function __construct(string $absolutePath)
    {
        $this->absolutePath = $absolutePath;
    }

    public function absolutePath(): string
    {
        return $this->absolutePath;
    }

    public function toString(): string
    {
        return sprintf(
            '%s - %s',
            get_class($this),
            $this->absolutePath
        );
    }
}
