<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\DeliveryLog;

/**
 * @extends AbstractProvider<NotificationConfig>
 */
final class NotificationProvider extends AbstractProvider
{
    public const DELIVERY_LOG = 'NOTIFICATION_DELIVERY_LOG';

    public const HEADERS = 'NOTIFICATION_HEADERS';

    #[Provides(self::DELIVERY_LOG)]
    public function deliveryLog(): DeliveryLog
    {
        return new DeliveryLog();
    }

    /**
     * The headers this module puts on every message. Deliberately a plain list
     * the application can wrap with `extendService()` -- a deployment that has
     * to tag its traffic should not have to fork this class to do it.
     *
     * @return array<string, string>
     */
    #[Provides(self::HEADERS)]
    public function headers(): array
    {
        return ['X-Invoicing-Source' => 'invoicing'];
    }
}
