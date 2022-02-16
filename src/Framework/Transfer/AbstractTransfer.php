<?php

declare(strict_types=1);

namespace Gacela\Framework\Transfer;

use function get_object_vars;
use function lcfirst;
use function preg_replace;
use function property_exists;
use function reset;

abstract class AbstractTransfer
{
    /**
     * @param array<string,mixed> $array
     *
     * @return static
     *
     * @psalm-suppress MixedAssignment
     */
    public function fromArray(array $array)
    {
        foreach ($array as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @return mixed|static
     */
    public function __call(string $name, array $arguments = [])
    {
        // fluent getters
        $withoutPrefix = (string)preg_replace('/^get/', '', $name);
        $normalizedName = lcfirst($withoutPrefix);
        if (property_exists($this, $normalizedName)) {
            return $this->{$normalizedName};
        }

        // fluent setters
        $withoutPrefix = (string)preg_replace('/^set/', '', $name);
        $normalizedName = lcfirst($withoutPrefix);
        if (property_exists($this, $normalizedName)) {
            $this->{$normalizedName} = reset($arguments);
            return $this;
        }

        throw UnknownPropertyException::withName($normalizedName);
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->__call($name);
    }

    /**
     * @param mixed $value
     *
     * @return mixed|static
     */
    public function __set(string $name, $value)
    {
        return $this->__call($name, [$value]);
    }

    public function __isset(string $name): bool
    {
        return $this->__call($name) !== null;
    }
}
