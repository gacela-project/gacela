<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleViolation;
use Gacela\StaticAnalysis\NamespaceMatch;

use function array_keys;

/**
 * The declared module rules, read against the graph the project actually has.
 *
 * Two findings come out of one pass, and both matter. A forbidden dependency is
 * the rule doing its job. A rule naming a module nobody has any more is the rule
 * having quietly stopped doing it -- the same failure the cycle allow list
 * guards against, for the same reason: a boundary that no longer matches
 * anything still reads as a boundary being watched.
 */
final class ModuleRuleChecker
{
    /**
     * @param array<string, list<string>> $graph module namespace => the modules it depends on
     */
    public function check(array $graph, ModuleRuleSet $rules): ModuleRuleCheckResult
    {
        $violations = [];

        foreach ($graph as $module => $dependencies) {
            foreach ($dependencies as $dependency) {
                $violation = $rules->violationFor($module, $dependency);
                if ($violation instanceof ModuleRuleViolation) {
                    $violations[] = $violation;
                }
            }
        }

        return new ModuleRuleCheckResult($violations, $this->unknownNamespaces($rules, array_keys($graph)));
    }

    /**
     * @param list<string> $modules
     *
     * @return list<string>
     */
    private function unknownNamespaces(ModuleRuleSet $rules, array $modules): array
    {
        $unknown = [];

        foreach ($rules->declaredNamespaces() as $namespace) {
            if (!$this->coversAnyModule($namespace, $modules)) {
                $unknown[] = $namespace;
            }
        }

        return $unknown;
    }

    /**
     * A namespace that names no module of its own still governs something when
     * modules live underneath it, which is how a rule about a whole area of the
     * app is written.
     *
     * @param list<string> $modules
     */
    private function coversAnyModule(string $namespace, array $modules): bool
    {
        foreach ($modules as $module) {
            if (NamespaceMatch::covers($namespace, $module)) {
                return true;
            }
        }

        return false;
    }
}
