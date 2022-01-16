<?php

declare(strict_types=1);

namespace Gacela\Framework;

abstract class AbstractConfigGacela
{
    /**
     * @return array<array>|array{type:string,path:string,path_local:string}
     */
    public function config(): array
    {
        return [];
    }

    /**
     * @param array<string,mixed> $globalServices
     *
     * @return array<class-string,class-string|callable>
     */
    public function mappingInterfaces(array $globalServices): array
    {
        return [];
    }
}
