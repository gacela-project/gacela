<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractConfigGacela
{
    /** @var array<string, mixed> */
    private array $globalServices;

    /**
     * @param array<string, mixed> $globalServices
     */
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

    /**
     * @return array<array>|array{type:string,path:string,path_local:string}
     */
    public function config(): array
    {
        return [];
    }

    /**
     * @return array<string,string|callable>
     */
    public function mappingInterfaces(): array
    {
        return [];
    }
}
