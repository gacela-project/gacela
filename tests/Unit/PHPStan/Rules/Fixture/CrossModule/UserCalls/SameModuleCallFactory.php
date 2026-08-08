<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls\Domain\UserCallsService;

final class SameModuleCallFactory
{
    public function __construct(
        private readonly UserCallsService $user,
    ) {
    }

    public function build(): string
    {
        return $this->user->run();
    }
}
