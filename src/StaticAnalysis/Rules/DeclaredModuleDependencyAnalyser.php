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
 *
 * `#[PublicApi]` is deliberately *not* honoured here, unlike in the two
 * cross-module rules. Those ask "may this class be touched without going through
 * the Facade", which is what publishing a class answers. This one asks whether
 * two modules may be coupled at all, and a published class does not make a
 * forbidden edge allowed -- the edge is the finding, whatever sits at the end
 * of it.
 *
 * `debug:graph --check` enforces the same rules file over module-to-module edges
 * built from imports, where no attribute is visible. Exempting here alone would
 * leave the editor green and the CI gate red on the same code, and the paragraph
 * above is what says why that is not worth doing.
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
        // No public-api segments: see the class docblock. What a module exports
        // is not what a rules file allows another module to depend on.
        $this->boundary = new ModuleBoundary($rootNamespace, $modulePathSegments, $sharedNamespaces, []);
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
