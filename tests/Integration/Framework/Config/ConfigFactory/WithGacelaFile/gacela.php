<?php

declare(strict_types=1);

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Setup\SetupGacela;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;

return static fn () => (new SetupGacela())
    ->setConfig(static function (ConfigBuilder $builder): void {
        $builder->add('config/from-gacela.php');
    })
    ->setGlobalServices(['CustomClass' => CustomClass::class])
    ->setMappingInterfaces(
        static function (MappingInterfacesBuilder $mappingInterfacesBuilder, array $globalServices): void {
            $mappingInterfacesBuilder->bind(CustomInterface::class, $globalServices['CustomClass']);
        },
    )
    ->setSuffixTypes(static function (SuffixTypesBuilder $suffixTypesBuilder): void {
        $suffixTypesBuilder
            ->addFactory('Fact')
            ->addConfig('Conf')
            ->addDependencyProvider('DepPro');
    });
