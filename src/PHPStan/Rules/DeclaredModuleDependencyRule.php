<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\ModuleRules\ModuleRuleSet;
use Gacela\StaticAnalysis\Rules\DeclaredModuleDependencyAnalyser;

/**
 * @see DeclaredModuleDependencyAnalyser for what is checked and why
 */
final class DeclaredModuleDependencyRule extends InClassAnalyserRule
{
    /**
     * The rules file is read once, when PHPStan builds its container. An
     * unreadable or malformed file throws there rather than being treated as
     * "no rules": a boundary check that quietly turns itself off is the one
     * failure this rule cannot have.
     *
     * @param string       $rulesFile        path to the same JSON file `debug:graph --check --rules` reads
     * @param list<string> $sharedNamespaces namespaces exempt from the rules
     *                                       (shared kernels): references into
     *                                       them are always allowed, and classes
     *                                       inside them are not checked
     */
    public function __construct(
        string $rootNamespace,
        string $rulesFile,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
    ) {
        parent::__construct(new DeclaredModuleDependencyAnalyser(
            $rootNamespace,
            ModuleRuleSet::fromFile($rulesFile),
            $modulePathSegments,
            $sharedNamespaces,
        ));
    }
}
