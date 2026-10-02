<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Shop\Audit;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\AsListener;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Log;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\OrderPlaced;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\ShopEvent;

use function assert;

final class AuditProvider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }

    #[AsListener(ShopEvent::class)]
    public function record(object $event): void
    {
        assert($event instanceof OrderPlaced);
        Log::$lines[] = 'audit ' . $event->id;
    }
}
