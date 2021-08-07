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
        trigger_error('gacela.json is deprecated. Use gacela.php instead.', E_USER_DEPRECATED);
        $this->configs = $configs;
        $this->mappingInterfaces = $mappingInterfaces;
    }

    /**
     * @param array{
     *     config: array<array>|array{type:string,path:string,path_local:string},
     *     mapping-interfaces: array<string,string|callable>,
     * } $json
     */
    public static function fromArray(array $json): self
    {
        return new self(
            self::getConfigItems($json['config'] ?? []),
            $json['mapping-interfaces'] ?? [],
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
    public function getConfigs(): array
    {
        return $this->configs;
    }

    /**
     * @return array<string,string|callable>
     */
    public function getMappingInterfaces(): array
    {
        return $this->mappingInterfaces;
    }
}
