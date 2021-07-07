<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class GacelaJsonConfig
{
    /** @var GacelaJsonConfigItem[] */
    private array $configs;

    private function __construct(GacelaJsonConfigItem ...$configs)
    {
        $this->configs = $configs;
    }

    /**
     * @param array{config: array<array>|array{type:string,path:string,path_local:string}} $array
     */
    public static function fromArray(array $array): self
    {
        $first = reset($array['config']);

        if (is_array($first)) {
            /** @var array<array{type:string,path:string,path_local:string}> $configs */
            $configs = $array['config'];
            $map = array_values(array_map(
                static fn (array $c) => GacelaJsonConfigItem::fromArray($c),
                $configs
            ));

            return new self(...$map);
        }

        return new self(GacelaJsonConfigItem::fromArray($array['config']));
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
