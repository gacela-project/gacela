<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigBuilder;

final class BindingsBuilder
{
    /** @var array<class-string,class-string|object|callable> */
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
     * @return array<class-string,class-string|object|callable>
     */
    public function build(): array
    {
        return $this->mapping;
    }
}
