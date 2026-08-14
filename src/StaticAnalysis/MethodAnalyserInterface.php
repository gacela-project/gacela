<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use PhpParser\Node\Stmt\ClassMethod;

/**
 * A rule that judges one method rather than the class around it.
 *
 * The sibling of {@see ClassAnalyserInterface}, and it exists for the reason
 * that one gives: having one shape for all of them is what lets a host register
 * them as a list and adapt them once, instead of once per rule.
 *
 * Without it the two method-level rules were named individually in both hosts —
 * a static property, a `??=` and a loop each in the Psalm plugin, and a PHPStan
 * `Rule` copied from its sibling. A third would have been a third copy.
 */
interface MethodAnalyserInterface
{
    /**
     * @return list<Violation>
     */
    public function analyse(ClassMethod $method, AnalysedClassInterface $class): array;
}
