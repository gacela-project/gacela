<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Setup\SetupGacela;
use Gacela\Framework\Setup\SetupGacelaFactory;
use Gacela\Framework\Setup\SetupGacelaInterface;
use function is_array;

final class Gacela
{
    public const CONFIG = 'config';
    public const MAPPING_INTERFACES = 'mapping-interfaces';
    public const SUFFIX_TYPES = 'suffix-types';
    public const GLOBAL_SERVICES = 'global-services';

    /**
     * Define the entry point of Gacela.
     *
     * @param array|SetupGacelaInterface|null $setup array to allow BC. Use SetupGacelaInterface instead.
     */
    public static function bootstrap(string $appRootDir, $setup = null): void
    {
        $setup = self::normalizeSetup($setup);

        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setSetup($setup)
            ->init();
    }

    /**
     * @param array|SetupGacelaInterface|null $setup
     */
    private static function normalizeSetup($setup = null): SetupGacelaInterface
    {
        if (is_array($setup)) {
            trigger_deprecation('Gacela', '0.14', 'Use SetupGacelaInterface instead');

            /**
             * @var array{
             *     config?: callable(\Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder):void,
             *     mapping-interfaces?: callable(\Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder, array<string,mixed>):void,
             *     suffix-types?: callable(\Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder):void,
             *     global-services?: array<string,mixed>,
             * } $setup
             */
            return SetupGacelaFactory::fromArray($setup);
        }

        return $setup ?? new SetupGacela();
    }
}
