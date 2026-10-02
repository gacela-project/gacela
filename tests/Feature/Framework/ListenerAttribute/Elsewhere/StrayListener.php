<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Elsewhere;

use Gacela\Framework\Attribute\AsListener;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Log;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\OrderPlaced;

final class StrayListener
{
    #[AsListener]
    public function onOrderPlaced(OrderPlaced $event): void
    {
        Log::$lines[] = 'stray ' . $event->id;
    }
}
