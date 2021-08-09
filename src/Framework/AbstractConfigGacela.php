<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractConfigGacela
{
    private array $globalServices;

    public function __construct(array $globalServices = [])
    {
        $this->globalServices = $globalServices;
    }

    /**
     * @return mixed
     */
    protected function getGlobalService(string $key)
    {
        return $this->globalServices[$key] ?? null;
    }

    public function config(): array
    {
        return [];
    }

    public function mappingInterfaces(): array
    {
        return [];
    }
}
