<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaConfigArgs;

final class MappingInterfacesBuilder
{
    /** @var array<class-string,class-string|object|callable> */
    private array $mapping = [];

    /**
     * @param class-string $key
     * @param class-string|object|callable $value
     */
    public function bind(string $key, $value): self
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
