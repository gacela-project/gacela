<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class GacelaJsonConfigItem
{
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
        $this->type = $type ?: 'php';
        $this->path = $path ?: 'config/*.php';
        $this->pathLocal = $pathLocal ?: 'config/local.php';
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
