<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\Framework\AbstractFactory;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;
use function str_ends_with;
use function strrpos;
use function substr;

/**
 * @implements Rule<InClassNode>
 */
final class FactoryDoesNotCallFacadeRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        if (!$classReflection->isSubclassOf(AbstractFactory::class)) {
            return [];
        }

        $classNode = $node->getOriginalNode();
        $nodeFinder = new NodeFinder();
        $errors = [];

        /** @var New_[] $newExpressions */
        $newExpressions = $nodeFinder->findInstanceOf($classNode, New_::class);
        foreach ($newExpressions as $new) {
            if (!$new->class instanceof Name) {
                continue;
            }

            $className = $new->class->toString();
            if (!str_ends_with($this->shortName($className), 'Facade')) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Factory %s must not instantiate a Facade (found: new %s). Depend on other modules through their Facade via the Provider.',
                $classReflection->getName(),
                $className,
            ))
                ->identifier('gacela.factoryInstantiatesFacade')
                ->build();
        }

        /** @var MethodCall[] $methodCalls */
        $methodCalls = $nodeFinder->findInstanceOf($classNode, MethodCall::class);
        foreach ($methodCalls as $call) {
            if (!$call->name instanceof Identifier) {
                continue;
            }

            if ($call->name->toString() !== 'getFacade') {
                continue;
            }

            if (!$call->var instanceof Variable) {
                continue;
            }

            if ($call->var->name !== 'this') {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Factory %s must not call $this->getFacade(); same-module access goes through the Factory itself, cross-module access goes through the Provider.',
                $classReflection->getName(),
            ))
                ->identifier('gacela.factoryCallsGetFacade')
                ->build();
        }

        return $errors;
    }

    private function shortName(string $className): string
    {
        $pos = strrpos($className, '\\');
        return $pos === false ? $className : substr($className, $pos + 1);
    }
}
