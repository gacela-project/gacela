<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container\Tagged;

final class PushValidator implements ValidatorInterface
{
    public function name(): string
    {
        return 'push';
    }
}
