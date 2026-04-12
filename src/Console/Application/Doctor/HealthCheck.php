<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor;

interface HealthCheck
{
    public function name(): string;

    public function run(): CheckResult;
}
