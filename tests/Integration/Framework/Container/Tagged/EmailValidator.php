<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container\Tagged;

final class EmailValidator implements ValidatorInterface
{
    public function name(): string
    {
        return 'email';
    }
}
