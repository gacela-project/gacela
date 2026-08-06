<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\Framework\AbstractFacade;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;
use function str_starts_with;

/**
 * Reports public Facade methods missing from the Facade's own `*FacadeInterface`.
 *
 * Only this direction can drift. PHP already rejects a class that fails to
 * implement an interface method, so the interface can never gain a method the
 * facade lacks -- but the facade grows public methods the interface never hears
 * about, and consumers type-hinting the interface silently cannot reach them.
 * That drift is invisible until someone reads both files side by side, and the
 * correction is breaking by then.
 *
 * The rule only applies when a facade explicitly implements the interface named
 * after it (`FooFacade` implements `FooFacadeInterface`), which is the author
 * opting in. A facade implementing unrelated interfaces is not drifting.
 *
 * @implements Rule<InClassNode>
 */
final class FacadeInterfaceInSyncRule implements Rule
{
    use ClassReflectionHelperTrait;

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

        if (!$this->extendsClass($classReflection, AbstractFacade::class)) {
            return [];
        }

        $facadeInterface = $this->matchingInterface($classReflection);
        if (!$facadeInterface instanceof \PHPStan\Reflection\ClassReflection) {
            return [];
        }

        $errors = [];

        foreach ($node->getOriginalNode()->getMethods() as $method) {
            $methodName = $method->name->toString();
            if (!$method->isPublic()) {
                continue;
            }

            if (str_starts_with($methodName, '__')) {
                continue;
            }

            if ($facadeInterface->hasMethod($methodName)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Facade method %s::%s() is missing from %s. Consumers type-hinting the interface cannot reach it: declare it in the interface, or make the method non-public.',
                $classReflection->getName(),
                $methodName,
                $facadeInterface->getName(),
            ))
                ->identifier('gacela.facadeInterfaceDrift')
                ->line($method->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function matchingInterface(ClassReflection $classReflection): ?ClassReflection
    {
        $expected = $this->shortClassName($classReflection->getName()) . 'Interface';

        foreach ($classReflection->getInterfaces() as $interface) {
            if ($this->shortClassName($interface->getName()) === $expected) {
                return $interface;
            }
        }

        return null;
    }
}
