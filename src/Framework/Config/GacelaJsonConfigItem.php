<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class GacelaJsonConfigItem
{
    public const DEFAULT_TYPE = 'php';

    private const DEFAULT_PATH = 'config/*.php';
    private const DEFAULT_PATH_LOCAL = 'config/local.php';

    private string $type;
    private string $path;
    private string $pathLocal;

    public static function fromArray(array $json): self
    {
        $type = (string)($json['type'] ?? '');
        $path = (string)($json['path'] ?? '');
        $pathLocal = (string)($json['path_local'] ?? '');

        return new self($type, $path, $pathLocal);
    }

    public static function withDefaults(): self
    {
        return new self('', '', '');
    }

    private function __construct(string $type, string $path, string $pathLocal)
    {
        $this->type = $type ?: self::DEFAULT_TYPE;
        $this->path = $path ?: self::DEFAULT_PATH;
        $this->pathLocal = $pathLocal ?: self::DEFAULT_PATH_LOCAL;
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
}
