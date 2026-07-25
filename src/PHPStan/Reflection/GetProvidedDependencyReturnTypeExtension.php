<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Reflection;

use Gacela\Framework\AbstractFactory;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function class_exists;
use function count;
use function interface_exists;

/**
 * Types `getProvidedDependency(Foo::class)` as `Foo`.
 *
 * The signature returns `mixed` because the key is a plain string, so every
 * call site restores the type by hand with a `@var` the analyser has to take on
 * faith -- and which keeps claiming the old type after the Provider changes.
 * When the key *is* a class-string, the type was never unknown; it was thrown
 * away at the boundary.
 *
 * Only a resolvable class or interface name is typed. A string key
 * (`'some.service'`) still returns mixed, because nothing in the type system
 * says what it resolves to.
 */
final class GetProvidedDependencyReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return AbstractFactory::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getProvidedDependency';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $args = $methodCall->getArgs();
        if (count($args) !== 1) {
            return null;
        }

        foreach ($scope->getType($args[0]->value)->getConstantStrings() as $constantString) {
            $className = $constantString->getValue();

            if (class_exists($className) || interface_exists($className)) {
                return new ObjectType($className);
            }
        }

        return null;
    }
}
