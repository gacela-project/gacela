<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class GacelaJsonConfig
{
    private string $type;
    private string $path;
    private string $pathLocal;

    public static function fromArray(array $json): self
    {
        $type = (string)($json['config']['type'] ?? 'php');
        $path = (string)($json['config']['path'] ?? 'config/*.php');
        $pathLocal = (string)($json['config']['path_local'] ?? 'config/local.php');

        return new self($type, $path, $pathLocal);
    }

    private function __construct(string $type, string $path, string $pathLocal)
    {
        $this->type = $type;
        $this->path = $path;
        $this->pathLocal = $pathLocal;
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
