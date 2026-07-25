<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function count;
use function in_array;
use function is_array;
use function is_string;
use function sort;

/**
 * The set of module cycles a reviewer has accepted, and the reason each was.
 *
 * A cycle in a Markdown file is a decision the tooling cannot see, which makes a
 * reviewed cycle and a cycle nobody noticed the same byte sequence. This makes
 * the decision machine-readable -- and, just as importantly, self-invalidating:
 * an allowance that no longer matches a real cycle is reported as loudly as an
 * undeclared cycle. An allow-list that outlives what it allows becomes fiction,
 * and then it is only a way to keep the check quiet.
 */
final class CycleAllowList
{
    /**
     * @param list<array{modules: list<string>, reason: string}> $entries
     */
    private function __construct(
        private readonly array $entries,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param list<mixed> $decoded
     */
    public static function fromDecodedJson(array $decoded): self
    {
        $entries = [];
        $position = 0;

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                throw MalformedCycleAllowListException::entryIsNotAnObject($position);
            }

            $modules = self::stringList($entry['modules'] ?? null);
            if (count($modules) < 2) {
                throw MalformedCycleAllowListException::missingModules($position);
            }

            $reason = $entry['reason'] ?? null;
            if (!is_string($reason) || $reason === '') {
                throw MalformedCycleAllowListException::missingReason($position);
            }

            sort($modules);
            $entries[] = ['modules' => $modules, 'reason' => $reason];
            ++$position;
        }

        return new self($entries);
    }

    /**
     * @param list<list<string>> $cycles
     */
    public function check(array $cycles): CycleCheckResult
    {
        $undeclared = [];
        foreach ($cycles as $cycle) {
            if (!$this->allows($cycle)) {
                $undeclared[] = $cycle;
            }
        }

        $stale = [];
        foreach ($this->entries as $entry) {
            if (!in_array($entry['modules'], $cycles, true)) {
                $stale[] = $entry['modules'];
            }
        }

        return new CycleCheckResult($undeclared, $stale);
    }

    /**
     * @param list<string> $cycle
     */
    public function reasonFor(array $cycle): ?string
    {
        foreach ($this->entries as $entry) {
            if ($entry['modules'] === $cycle) {
                return $entry['reason'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $cycle
     */
    private function allows(array $cycle): bool
    {
        return $this->reasonFor($cycle) !== null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];

        /** @var mixed $item */
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
