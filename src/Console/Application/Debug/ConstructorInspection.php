<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use function count;

final class ConstructorInspection
{
    /**
     * @param class-string $className
     * @param list<ParameterInspection> $parameters
     */
    public function __construct(
        public readonly string $className,
        public readonly bool $hasConstructor,
        public readonly array $parameters,
    ) {
    }

    public function resolvableCount(): int
    {
        return count($this->filterByResolvable(true));
    }

    public function unresolvableCount(): int
    {
        return count($this->filterByResolvable(false));
    }

    public function isFullyResolvable(): bool
    {
        return $this->unresolvableCount() === 0;
    }

    /**
     * @return list<ParameterInspection>
     */
    public function unresolvableParameters(): array
    {
        return $this->filterByResolvable(false);
    }

    /**
     * @return list<ParameterInspection>
     */
    private function filterByResolvable(bool $resolvable): array
    {
        $result = [];
        foreach ($this->parameters as $parameter) {
            if ($parameter->isResolvable() === $resolvable) {
                $result[] = $parameter;
            }
        }

        return $result;
    }
}
