<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerDefinitions\Notifying;

final class SmsNotifier implements NotifierInterface
{
    public function name(): string
    {
        return 'sms';
    }
}
