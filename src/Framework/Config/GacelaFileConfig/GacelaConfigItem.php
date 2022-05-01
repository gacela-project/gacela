<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderInterface;

final class GacelaConfigItem
{
    private string $path;
    private string $pathLocal;
    private ConfigReaderInterface $reader;

    public function __construct(
        string $path,
        string $pathLocal = '',
        ?ConfigReaderInterface $reader = null
    ) {
        $this->path = $path;
        $this->pathLocal = $pathLocal;
        $this->reader = $reader ?? new PhpConfigReader();
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
