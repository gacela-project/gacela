<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use PhpParser\Node\Stmt\ClassLike;

/**
 * A rule that judges a class as a whole.
 *
 * Having one shape for all of them is what lets a host register them as a list
 * and adapt them once, instead of once per rule.
 */
interface ClassAnalyserInterface
{
    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array;
}
