<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use PHPUnit\Framework\TestCase;

final class GacelaConfigTest extends TestCase
{
    public function test_default_php_config_registers_default_paths(): void
    {
        $closure = GacelaConfig::defaultPhpConfig();
        $config = new GacelaConfig();

        $closure($config);

        $dto = $config->toTransfer();

        $paths = $dto->appConfigBuilder->build();
        self::assertCount(1, $paths);
        self::assertSame('config/*.php', $paths[0]->path());
        self::assertSame('config/local.php', $paths[0]->pathLocal());
    }

    public function test_add_mapping_interface_is_an_alias_of_add_binding(): void
    {
        $a = new GacelaConfig();
        $b = new GacelaConfig();

        $a->addMappingInterface('App\\Port', 'App\\Adapter');
        $b->addBinding('App\\Port', 'App\\Adapter');

        self::assertEquals(
            $a->toTransfer()->bindingsBuilder->build(),
            $b->toTransfer()->bindingsBuilder->build(),
        );
    }
}
