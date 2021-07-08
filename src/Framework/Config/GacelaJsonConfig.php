<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function is_array;

final class GacelaJsonConfig
{
    /** @var GacelaJsonConfigItem[] */
    private array $configs;

    private function __construct(GacelaJsonConfigItem ...$configs)
    {
        $this->configs = $configs;
    }

    /**
     * @param array{config: array<array>|array{type:string,path:string,path_local:string}} $json
     */
    public static function fromArray(array $json): self
    {
        return new self(
            ...self::getConfigItems($json)
        );
    }

    /**
     * @param array{config: array<array>|array{type:string,path:string,path_local:string}} $json
     *
     * @return GacelaJsonConfigItem[]
     */
    private static function getConfigItems(array $json): array
    {
        $first = reset($json['config']);

        if (!is_array($first)) {
            return [GacelaJsonConfigItem::fromArray($json['config'])];
        }

        /** @var array<array{type:string,path:string,path_local:string}> $configs */
        $configs = $json['config'];

        return array_values(array_map(
            static fn (array $c) => GacelaJsonConfigItem::fromArray($c),
            $configs
        ));
    }

    public static function withDefaults(): self
    {
        return new self(GacelaJsonConfigItem::withDefaults());
    }

    /**
     * @return GacelaJsonConfigItem[]
     */
    public function configs(): array
    {
        return $this->configs;
    }
}
