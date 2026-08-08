<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFactory;
use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

use function sprintf;
use function str_ends_with;

/**
 * A Factory wires its own module up; it does not reach for a Facade to do it.
 *
 * Both halves are reported separately because they are different mistakes with
 * different corrections: instantiating a Facade bypasses the Provider, calling
 * `$this->getFacade()` bypasses the Factory itself.
 */
final class FactoryDoesNotCallFacadeAnalyser implements ClassAnalyserInterface
{
    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        if (!$class->extendsClass(AbstractFactory::class)) {
            return [];
        }

        $nodeFinder = new NodeFinder();

        return [
            ...$this->instantiatedFacades($nodeFinder, $node, $class),
            ...$this->getFacadeCalls($nodeFinder, $node, $class),
        ];
    }

    /**
     * @return list<Violation>
     */
    private function instantiatedFacades(NodeFinder $nodeFinder, ClassLike $node, AnalysedClassInterface $class): array
    {
        $violations = [];

        /** @var list<New_> $newExpressions */
        $newExpressions = $nodeFinder->findInstanceOf($node, New_::class);
        foreach ($newExpressions as $new) {
            // `new $class` and `new class {}` name nothing to match on.
            if (!$new->class instanceof Name) {
                continue;
            }

            $className = $new->class->toString();
            if (!str_ends_with(ShortName::of($className), 'Facade')) {
                continue;
            }

            $violations[] = new Violation(
                sprintf(
                    'Factory %s must not instantiate a Facade (found: new %s). Depend on other modules through their Facade via the Provider.',
                    $class->name(),
                    $className,
                ),
                'gacela.factoryInstantiatesFacade',
            );
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function getFacadeCalls(NodeFinder $nodeFinder, ClassLike $node, AnalysedClassInterface $class): array
    {
        $violations = [];

        /** @var list<MethodCall> $methodCalls */
        $methodCalls = $nodeFinder->findInstanceOf($node, MethodCall::class);
        foreach ($methodCalls as $call) {
            if (!$this->isThisGetFacade($call)) {
                continue;
            }

            $violations[] = new Violation(
                sprintf(
                    'Factory %s must not call $this->getFacade(); same-module access goes through the Factory itself, cross-module access goes through the Provider.',
                    $class->name(),
                ),
                'gacela.factoryCallsGetFacade',
            );
        }

        return $violations;
    }

    private function isThisGetFacade(MethodCall $call): bool
    {
        if (!$call->name instanceof Identifier || $call->name->toString() !== 'getFacade') {
            return false;
        }

        return $call->var instanceof Variable && $call->var->name === 'this';
    }
}
