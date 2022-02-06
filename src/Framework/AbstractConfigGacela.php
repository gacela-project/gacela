<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\ConfigReaderInterface;

abstract class AbstractConfigGacela
{
    /**
     * @return array<array>|array{
     *     type?:string,
     *     path?:string,
     *     path_local?:string
     * }
     */
    public function config(): array
    {
        return [];
    }

    /**
     * @return array<string,ConfigReaderInterface>
     */
    public function configReaders(): array
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
