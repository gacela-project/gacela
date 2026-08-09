<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\ModuleRules;

/**
 * One dependency a rule forbids, and the reason somebody wrote down for it.
 *
 * The reason travels with the finding rather than being looked up again by
 * whoever reports it: a violation that cannot say why it is one leaves the
 * reader with a rule file to go read.
 */
final class ModuleRuleViolation
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $reason,
    ) {
    }
}
