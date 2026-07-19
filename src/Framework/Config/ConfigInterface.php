<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Exception\ConfigException;

interface ConfigInterface
{
    public const DEFAULT_CONFIG_VALUE = 'Gacela\Framework\Config::DEFAULT_CONFIG_VALUE';

    /**
     * @throws ConfigException
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = self::DEFAULT_CONFIG_VALUE);

    /**
     * @throws ConfigException
     */
    public function getString(string $key, ?string $default = null): string;

    /**
     * @throws ConfigException
     */
    public function getInt(string $key, ?int $default = null): int;

    /**
     * @throws ConfigException
     */
    public function getFloat(string $key, ?float $default = null): float;

    /**
     * @throws ConfigException
     */
    public function getBool(string $key, ?bool $default = null): bool;

    /**
     * @param array<array-key,mixed>|null $default
     *
     * @throws ConfigException
     *
     * @return array<array-key,mixed>
     */
    public function getArray(string $key, ?array $default = null): array;

    /**
     * Return the effective merged configuration (all sources combined).
     *
     * @throws ConfigException
     *
     * @return array<string,mixed>
     */
    public function getAllValues(): array;

    public function getSetupGacela(): SetupGacelaInterface;

    public function hasKey(string $key): bool;
}
