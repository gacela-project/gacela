<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaConfigArgs\MappingInterfacesResolver;
use Gacela\Framework\Config\GacelaConfigArgs\SuffixTypesResolver;

abstract class AbstractConfigGacela
{
    /**
     * e.g:
     * <code>
     * return [
     *   'path' => 'config/*.php',
     *   'path_local' => 'config/local.php',
     *   'reader' => new PhpConfigReader(),
     * ],
     * # OR
     * return [
     *   [
     *     'path' => '.env*',
     *     'path_local' => '.env',
     *     'reader' => new EnvConfigReader(),
     *   ],
     *   [
     *     'path' => 'config/*.php',
     *     'path_local' => 'config/local.php',
     *     'reader' => new PhpConfigReader(),
     *   ],
     * ];
     * </code>
     *
     * <b>path</b>: Define the path where Gacela will read all the config files. Default: <i>config/*.php</i><br>
     * <b>path_local</b>: Define the path where Gacela will read the local config file. Default: <i>config/local.php</i><br>
     * <b>reader</b>: Define the reader class which will read and parse the config files. Default: <i>new PhpConfigReader()</i><br>
     *
     * @return list<array{path?:string, path_local?:string, reader?:ConfigReaderInterface}>|array{path?:string, path_local?:string, reader?:ConfigReaderInterface}
     */
    public function config(): array
    {
        return [];
    }

    /**
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
     *
     * @param array<string,mixed> $globalServices
     */
    public function mappingInterfaces(MappingInterfacesResolver $interfacesResolver, array $globalServices): void
    {
    }

    /**
     * Allow overriding gacela resolvable types.
     */
    public function suffixTypes(SuffixTypesResolver $suffixTypesResolver): void
    {
    }
}
