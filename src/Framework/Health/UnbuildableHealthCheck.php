<?php

declare(strict_types=1);

namespace Gacela\Framework\Health;

use Throwable;

use function strrpos;
use function substr;

final class UnbuildableHealthCheck implements ModuleHealthCheckInterface
{
    /**
     * @param class-string $className
     */
    public function __construct(
        private readonly string $className,
        private readonly Throwable $exception,
    ) {
    }

    public function checkHealth(): HealthStatus
    {
        return HealthStatus::unhealthy(
            'Health check could not be built: ' . $this->exception->getMessage(),
            ['exception' => $this->exception::class],
        );
    }

    public function getModuleName(): string
    {
        $separator = strrpos($this->className, '\\');

        return $separator === false ? $this->className : substr($this->className, $separator + 1);
    }
}
