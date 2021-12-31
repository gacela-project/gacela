<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

final class GacelaConfigItem
{
    public const DEFAULT_TYPE = 'php';

    private const DEFAULT_PATH = 'config/*.php';
    private const DEFAULT_PATH_LOCAL = 'config/local.php';

    private string $type;
    private string $path;
    private string $pathLocal;

    public function __construct(
        string $type = self::DEFAULT_TYPE,
        string $path = self::DEFAULT_PATH,
        string $pathLocal = self::DEFAULT_PATH_LOCAL
    ) {
        $this->type = $type;
        $this->path = $path;
        $this->pathLocal = $pathLocal;
    }

    /**
     * @param array<array-key, mixed> $item
     */
    public static function fromArray(array $item): self
    {
        /** @var null|string $type */
        $type = $item['type'] ?? null;
        /** @var null|string $path */
        $path = $item['path'] ?? null;
        /** @var null|string $pathLocal */
        $pathLocal = $item['path_local'] ?? null;

        return new self(
            $type ?? self::DEFAULT_TYPE,
            $path ?? self::DEFAULT_PATH,
            $pathLocal ?? self::DEFAULT_PATH_LOCAL
        );
    }

    public static function withDefaults(): self
    {
        return new self();
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

    public function __toString(): string
    {
        return sprintf(
            'GacelaConfigItem{ type:%s, path:%s, pathLocal:%s }',
            $this->type,
            $this->path,
            $this->pathLocal
        );
    }
}
