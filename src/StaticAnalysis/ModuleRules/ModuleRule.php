<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\ModuleRules;

use Gacela\StaticAnalysis\NamespaceMatch;

/**
 * One declared boundary: what a module may reach, or what it may not.
 *
 * The two directions are the same rule read from opposite ends. `deny` names the
 * edges that are forbidden and leaves everything else alone; `allow` names the
 * only edges permitted and forbids the rest. A module cannot be governed by both
 * at once, because the two would answer the same question separately.
 */
final class ModuleRule
{
    /**
     * @param list<string> $namespaces  what the rule names -- forbidden, or exclusively permitted
     * @param bool         $isAllowList which of the two the list means
     */
    private function __construct(
        public readonly string $from,
        private readonly array $namespaces,
        private readonly bool $isAllowList,
        public readonly string $reason,
    ) {
    }

    /**
     * @param list<string> $namespaces
     */
    public static function deny(string $from, array $namespaces, string $reason): self
    {
        return new self($from, $namespaces, false, $reason);
    }

    /**
     * @param list<string> $namespaces an empty list is meaningful: a module that
     *                                 may reach nothing outside itself
     */
    public static function allow(string $from, array $namespaces, string $reason): self
    {
        return new self($from, $namespaces, true, $reason);
    }

    /**
     * Submodules included: a rule about `App\Payment` governs
     * `App\Payment\Refunds`, which is part of the same module tree.
     */
    public function governs(string $module): bool
    {
        return NamespaceMatch::covers($this->from, $module);
    }

    public function forbids(string $module): bool
    {
        if (!$this->isAllowList) {
            return NamespaceMatch::anyCovers($this->namespaces, $module);
        }

        // Reaching deeper into the declaring module's own tree is not a
        // dependency the rule is about; an allow list names what is *outside*.
        if (NamespaceMatch::covers($this->from, $module)) {
            return false;
        }

        return !NamespaceMatch::anyCovers($this->namespaces, $module);
    }

    /**
     * @return list<string>
     */
    public function namespaces(): array
    {
        return [$this->from, ...$this->namespaces];
    }
}
