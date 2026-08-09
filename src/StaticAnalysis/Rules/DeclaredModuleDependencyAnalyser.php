<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ModuleBoundary;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use Gacela\StaticAnalysis\ModuleRules\ModuleRuleViolation;
use Gacela\StaticAnalysis\ReferencedClasses;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Stmt\ClassLike;

use function sprintf;

/**
 * The dependencies a project declared it would not have, checked where they are
 * written rather than after the fact.
 *
 * `debug:graph --check` reads the same rules over the whole graph, which is the
 * CI gate; this is the same decision arriving in the editor, on the line that
 * creates the edge. One rule file feeds both, because a boundary that holds in
 * one place and not the other is a boundary nobody trusts.
 *
 * Cycles are the one thing the graph could refuse on its own. Everything else --
 * billing must not reach back-office, reporting reads and nothing more -- is a
 * decision that has to be written down before any tool can hold anyone to it.
 */
final class DeclaredModuleDependencyAnalyser implements ClassAnalyserInterface
{
    private readonly ModuleBoundary $boundary;

    /**
     * @param list<string> $sharedNamespaces namespaces exempt from the rules
     *                                       (shared kernels): references into
     *                                       them are always allowed, and classes
     *                                       inside them are not checked
     */
    public function __construct(
        string $rootNamespace,
        private readonly ModuleRuleSet $rules,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
    ) {
        $this->boundary = new ModuleBoundary($rootNamespace, $modulePathSegments, $sharedNamespaces);
    }

    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        if ($this->rules->isEmpty()) {
            return [];
        }

        $currentClass = $class->name();
        if ($this->boundary->isShared($currentClass)) {
            return [];
        }

        $currentModule = $this->boundary->moduleOf($currentClass);
        if ($currentModule === null) {
            return [];
        }

        $violations = [];
        $seen = [];

        foreach (ReferencedClasses::in($node) as $referenced) {
            $referencedModule = $this->boundary->crossedBy($currentModule, $referenced);
            if ($referencedModule === null) {
                continue;
            }

            // One forbidden module reached from twenty places is one boundary to
            // fix, and the rule that forbids it is the same rule every time.
            if (isset($seen[$referencedModule])) {
                continue;
            }

            $violation = $this->rules->violationFor($currentModule, $referencedModule);
            if (!$violation instanceof ModuleRuleViolation) {
                continue;
            }

            $seen[$referencedModule] = true;
            $violations[] = new Violation(
                sprintf(
                    '%s must not depend on %s: %s',
                    $currentModule,
                    $referencedModule,
                    $violation->reason,
                ),
                'gacela.declaredModuleDependency',
                sprintf('Drop the dependency on %s, or change the rule that forbids it.', $referencedModule),
            );
        }

        return $violations;
    }
}
