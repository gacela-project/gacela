<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container\Resolving;

interface LoggerAwareInterface
{
    public function setLogger(string $logger): void;

    public function logger(): ?string;
}
