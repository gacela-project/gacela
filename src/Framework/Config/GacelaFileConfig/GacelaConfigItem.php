<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderInterface;

final class GacelaConfigItem
{
    public function __construct(
        private readonly string $path,
        private readonly string $pathLocal = '',
        private readonly ConfigReaderInterface $reader = new PhpConfigReader(),
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function pathLocal(): string
    {
        return $this->pathLocal;
    }

    public function reader(): ConfigReaderInterface
    {
        return $this->reader;
    }
}
