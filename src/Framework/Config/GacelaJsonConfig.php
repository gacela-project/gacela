<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function is_array;

final class GacelaJsonConfig
{
    /** @var array<string,GacelaJsonConfigItem> */
    private array $configs;

    /** @var array<string,list<string>> */
    private array $dependencies;

    /**
     * @param array<string,GacelaJsonConfigItem> $configs
     * @param array<string,list<string>> $dependencies
     */
    private function __construct(
        array $configs,
        array $dependencies
    ) {
        $this->configs = $configs;
        $this->dependencies = $dependencies;
    }

    /**
     * @param array{
     *     config: array<array>|array{type:string,path:string,path_local:string},
     *     dependencies: array<string,list<string>>,
     * } $json
     */
    public static function fromArray(array $json): self
    {
        return new self(
            self::getConfigItems($json['config'] ?? []),
            $json['dependencies'] ?? [],
        );
    }

    /**
     * @param array<array>|array{type:string,path:string,path_local:string} $config
     *
     * @return array<string,GacelaJsonConfigItem>
     */
    private static function getConfigItems(array $config): array
    {
        $first = reset($config);

        if (!is_array($first)) {
            $c = GacelaJsonConfigItem::fromArray($config);
            return [$c->type() => $c];
        }

        $result = [];

        /** @var array<array{type:string,path:string,path_local:string}> $config */
        foreach ($config as $configItem) {
            $c = GacelaJsonConfigItem::fromArray($configItem);
            $result[$c->type()] = $c;
        }

        return $result;
    }

    public static function withDefaults(): self
    {
        $configItem = GacelaJsonConfigItem::withDefaults();

        return new self(
            [$configItem->type() => $configItem],
            []
        );
    }

    /**
     * @return array<string,GacelaJsonConfigItem>
     */
    public function configs(): array
    {
        return $this->configs;
    }

    /**
     * @return array<string,list<string>>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }
}
