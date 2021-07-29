<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class GacelaJsonConfigItem
{
    public const DEFAULT_TYPE = 'php';

    private const DEFAULT_PATH = 'config/*.php';
    private const DEFAULT_PATH_LOCAL = 'config/local.php';
    private const DEFAULT_IS_OPTIONAL = false;

    private string $type;
    private string $path;
    private string $pathLocal;
    private bool $isOptional;

    public static function fromArray(array $json): self
    {
        return new self(
            (string)($json['type'] ?? self::DEFAULT_TYPE),
            (string)($json['path'] ?? self::DEFAULT_PATH),
            (string)($json['path_local'] ?? self::DEFAULT_PATH_LOCAL),
            (bool)($json['optional'] ?? self::DEFAULT_IS_OPTIONAL)
        );
    }

    public static function withDefaults(): self
    {
        return new self();
    }

    private function __construct(
        string $type = self::DEFAULT_TYPE,
        string $path = self::DEFAULT_PATH,
        string $pathLocal = self::DEFAULT_PATH_LOCAL,
        bool $isOptional = self::DEFAULT_IS_OPTIONAL
    ) {
        $this->type = $type;
        $this->path = $path;
        $this->pathLocal = $pathLocal;
        $this->isOptional = $isOptional;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function pathLocal(): string
    {
        return $this->pathLocal;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }
}
