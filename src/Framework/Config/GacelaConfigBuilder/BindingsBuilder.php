<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigBuilder;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

/**
 * @psalm-import-type BindingsMap from GacelaConfigFileInterface
 */
final class BindingsBuilder
{
    /** @var BindingsMap */
    private array $mapping = [];

    /**
     * @param class-string $key
     * @param callable|object|class-string $value
     */
    public function bind(string $key, callable|object|string $value): self
    {
        $this->mapping[$key] = $value;

        return $this;
    }

    /**
     * Bind a value only when the key is not already bound (register-unless-overridden).
     *
     * @param class-string $key
     * @param callable|object|class-string $value
     */
    public function bindIf(string $key, callable|object|string $value): self
    {
        if (!isset($this->mapping[$key])) {
            $this->mapping[$key] = $value;
        }

        return $this;
    }

    /**
     * @return BindingsMap
     */
    public function build(): array
    {
        return $this->mapping;
    }
}
