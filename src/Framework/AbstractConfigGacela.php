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
     * e.g:
     * <code>
     * return [
     *     // It instantiates the specific class on runtime
     *     AbstractClass::class => SpecificClass::class,
     *
     *     // It resolves the callable on runtime
     *     InterfaceClass::class => fn() => new SpecificClass($dependencies),
     * ];
     * </code>
     *
     * Define the mapping between interfaces and concretions, so Gacela services will auto-resolve them automatically.
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
     * e.g:
     * <code>
     * return [
     *     'Factory' => 'Creator',
     *     'Config' => 'Configuration',
     *     'DependencyProvider' => 'Binding',
     * ];
     * </code>
     *
     * Allow overriding gacela resolvable types.
     *
     * @return array{Factory?:string,Config?:string,DependencyProvider?:string}
     */
    public function overrideResolvableTypes(): array
    {
        return [];
    }
}
