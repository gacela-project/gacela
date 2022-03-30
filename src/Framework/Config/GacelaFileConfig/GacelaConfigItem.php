<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\ConfigReaderInterface;

final class GacelaConfigItem
{
    public const DEFAULT_PATH = 'config/*.php';
    public const DEFAULT_PATH_LOCAL = 'config/local.php';

    private string $path;
    private string $pathLocal;
    private ConfigReaderInterface $reader;

    public function __construct(
        string $path,
        string $pathLocal = self::DEFAULT_PATH_LOCAL,
        ?ConfigReaderInterface $reader = null
    ) {
        $this->path = $path;
        $this->pathLocal = $pathLocal;
        $this->reader = $reader ?? new PhpConfigReader();
    }

    public static function withDefaults(): self
    {
        return new self(self::DEFAULT_PATH);
    }

    public static function withPath(string $path): self
    {
        return new self($path);
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
