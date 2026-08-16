<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification;

use Gacela\Framework\AbstractConfig;

final class NotificationConfig extends AbstractConfig
{
    public function subjectPrefix(): string
    {
        return $this->getString('notification.subject_prefix');
    }
}
