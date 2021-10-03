<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigWithBootstrapSetup;

use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, [
            'config' => [
                'type' => 'php',
                'path' => 'custom-config.php',
                'path_local' => 'custom-config_local.php',
            ],
        ]);
    }

    public function test_load_default_config(): void
    {
        $facade = new LocalConfig\Facade();

        self::assertSame(
            [
                'config' => 1,
                'config_local' => 2,
                'override' => 5,
            ],
            $facade->doSomething()
        );
    }
}
