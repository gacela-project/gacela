<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListenerAttribute\Shop\Mailer;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\AsListener;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Log;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\OrderPlaced;

final class MailerFacade extends AbstractFacade
{
    #[AsListener]
    public function onOrderPlaced(OrderPlaced $event): void
    {
        Log::$lines[] = 'mailer ' . $event->id;
    }
}
