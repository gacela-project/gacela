<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container\Resolving;

final class ReportService implements LoggerAwareInterface
{
    private ?string $logger = null;

    public function setLogger(string $logger): void
    {
        $this->logger = $logger;
    }

    public function logger(): ?string
    {
        return $this->logger;
    }
}
