<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\ConfigReaderInterface;

abstract class AbstractConfigGacela
{
    /**
     * e.g:
     * <code>
     * return [
     *   'path' => '.env*',
     *   'path_local' => '.env',
     * ];
     * </code>
     *
     * <b>path</b>: Define the path where Gacela will read all the config files. Default: <i>config/*.php</i><br>
     * <b>path_local</b>: Define the path where Gacela will read the local config file. Default: <i>config/local.php</i>
     *
     * @return array<array>|array{
     *     path?:string,
     *     path_local?:string
     * }
     */
    public function config(): array
    {
        return [];
    }

    /**
     * e.g:
     * <code>
     * return ['php' => new \Gacela\Framework\Config\ConfigReader\PhpConfigReader()];
     * </code>
     *
     * Define the reader class which will read and parse the config files. Default: <i>PhpConfigReader</i>
     *
     * @return array<string,ConfigReaderInterface>
     */
    public function configReaders(): array
    {
        return [];
    }

    /**
     * e.g:
     * <code>
     * return [
     *     Abstractclass::class => new SpecificClass($dependencies),
     * ];
     * </code>
     *
     * Define the mapping between interfaces and concretions, so, Gacela will auto-resolve them automatically.
     *
     * @param array<string,mixed> $globalServices
     *
     * @return array<class-string,class-string|callable>
     */
    public function mappingInterfaces(array $globalServices): array
    {
        return [];
    }

    /**
     * Define path/s where Gacela should check when an interface should be auto-resolve.
     *
     * @return list<string>
     */
    public function customServicePaths(): array
    {
        return [];
    }
}
