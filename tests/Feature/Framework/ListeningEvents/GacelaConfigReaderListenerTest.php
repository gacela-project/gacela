<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\EventListener\ConfigReader\GacelaConfigReaderListener;
use Gacela\Framework\EventListener\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\EventListener\GacelaEventInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class GacelaConfigReaderListenerTest extends TestCase
{
    /** @var list<GacelaEventInterface> */
    private static array $inMemoryEvents = [];

    public function test_resolved_class_created(): void
    {
        self::$inMemoryEvents = [];

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.php');
            $config->resetInMemoryCache();
            $config->addEventListener(
                GacelaConfigReaderListener::class,
                static function (GacelaEventInterface $event): void {
                    self::$inMemoryEvents[] = $event;
                }
            );
        });

        self::assertEquals([
            new ReadPhpConfigEvent(ClassInfo::from('')),
        ], self::$inMemoryEvents);
    }
}
