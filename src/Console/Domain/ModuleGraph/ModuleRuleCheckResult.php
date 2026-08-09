<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use Gacela\StaticAnalysis\ModuleRules\ModuleRuleViolation;

final class ModuleRuleCheckResult
{
    /**
     * @param list<ModuleRuleViolation> $violations        dependencies a rule forbids
     * @param list<string>              $unknownNamespaces namespaces the rules name that no module answers to
     */
    public function __construct(
        public readonly array $violations,
        public readonly array $unknownNamespaces,
    ) {
    }

    public function isClean(): bool
    {
        return $this->violations === [] && $this->unknownNamespaces === [];
    }
}
