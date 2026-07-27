<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container\Tagged;

final class SmsValidator implements ValidatorInterface
{
    public function name(): string
    {
        return 'sms';
    }
}
