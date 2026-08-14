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
     * Parameters the container cannot satisfy, having looked.
     *
     * Narrower than {@see unresolvableCount()}, which also counts the ones the
     * inspector declined to read: a union-typed parameter is not walked, and
     * failing a build over it would blame a project for a gap in this tool.
     */
    public function faultCount(): int
    {
        $faults = 0;

        foreach ($this->parameters as $parameter) {
            if ($parameter->status->isFault()) {
                ++$faults;
            }
        }

        return $faults;
    }

    /**
     * Parameters nothing here has an opinion about, reported so a clean run
     * does not read as "everything was checked".
     */
    public function notInspectedCount(): int
    {
        $notInspected = 0;

        foreach ($this->parameters as $parameter) {
            if ($parameter->status->isNotInspected()) {
                ++$notInspected;
            }
        }

        return $notInspected;
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
