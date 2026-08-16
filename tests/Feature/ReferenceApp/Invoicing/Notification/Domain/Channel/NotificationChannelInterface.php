<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel;

use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\NotificationMessage;

/**
 * The extension point every delivery channel implements.
 *
 * Filled with `GacelaConfig::addPluginStack()`, so a package can add a channel
 * without this module knowing it exists.
 */
interface NotificationChannelInterface
{
    public function name(): string;

    /**
     * @return string the delivery receipt this channel produced
     */
    public function deliver(NotificationMessage $message): string;
}
