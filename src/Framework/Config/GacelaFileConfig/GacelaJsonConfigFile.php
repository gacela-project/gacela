<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use function is_array;

/**
 * @psalm-suppress DeprecatedClass
 *
 * @deprecated use GacelaPhpConfigFile instead
 */
final class GacelaJsonConfigFile implements GacelaConfigFileInterface
{
    /** @var array<string,GacelaConfigItemInterface> */
    private array $configs;

    /** @var array<string,string|callable> */
    private array $mappingInterfaces;

    /**
     * @param array<string,GacelaConfigItemInterface> $configs
     * @param array<string,string|callable> $mappingInterfaces
     */
    private function __construct(
        array $configs,
        array $mappingInterfaces
    ) {
        $this->configs = $configs;
        $this->mappingInterfaces = $mappingInterfaces;
    }

    /**
     * @param array{
     *     config: array<array>|array{type:string,path:string,path_local:string},
     *     dependencies: array<string,string|callable>,
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
     * @return array<string,GacelaConfigItemInterface>
     */
    private static function getConfigItems(array $config): array
    {
        $first = reset($config);

        if (!is_array($first)) {
            /** @psalm-suppress DeprecatedClass */
            $c = GacelaJsonConfigItem::fromArray($config);
            return [$c->type() => $c];
        }

        $result = [];

        /** @var array<array{type:string,path:string,path_local:string}> $config */
        foreach ($config as $configItem) {
            /** @psalm-suppress DeprecatedClass */
            $c = GacelaJsonConfigItem::fromArray($configItem);
            $result[$c->type()] = $c;
        }

        return $result;
    }

    public static function withDefaults(): self
    {
        /** @psalm-suppress DeprecatedClass */
        $configItem = GacelaJsonConfigItem::withDefaults();

        return new self(
            [$configItem->type() => $configItem],
            []
        );
    }

    /**
     * @return array<string,GacelaConfigItemInterface>
     */
    public function configs(): array
    {
        return $this->configs;
    }

    /**
     * @return array<string,string|callable>
     */
    public function mappingInterfaces(): array
    {
        return $this->mappingInterfaces;
    }
}
