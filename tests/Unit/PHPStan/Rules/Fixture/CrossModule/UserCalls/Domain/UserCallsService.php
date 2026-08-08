<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls\Domain;

final class UserCallsService
{
    public function run(): string
    {
        return 'user';
    }
}
