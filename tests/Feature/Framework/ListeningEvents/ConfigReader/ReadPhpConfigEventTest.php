<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\ConfigReader;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ReadPhpConfigEventTest extends TestCase
{
    /** @var list<GacelaEventInterface> */
    private static array $inMemoryEvents = [];

    protected function setUp(): void
    {
        self::$inMemoryEvents = [];
    }

    public function test_two_php_config_files(): void
    {
        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.php');
            $config->resetInMemoryCache();
            $config->registerSpecificListener(ReadPhpConfigEvent::class, [$this, 'saveInMemoryEvent']);
        });

        self::assertEquals([
            new ReadPhpConfigEvent(__DIR__ . '/config/default.php'),
            new ReadPhpConfigEvent(__DIR__ . '/config/local.php'),
        ], self::$inMemoryEvents);
    }

    public function test_no_yaml_config_files_found(): void
    {
        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.{yaml,yml}');
            $config->resetInMemoryCache();
            $config->registerSpecificListener(ReadPhpConfigEvent::class, [$this, 'saveInMemoryEvent']);
        });

        self::assertEmpty(self::$inMemoryEvents);
    }

    public function saveInMemoryEvent(GacelaEventInterface $event): void
    {
        self::$inMemoryEvents[] = $event;
    }
}
