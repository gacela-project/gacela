<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use PHPStan\Reflection\ClassReflection;

/**
 * The {@see AnalysedClassInterface} seam, answered from PHPStan's reflection.
 *
 * @internal
 */
final class ReflectionAnalysedClass implements AnalysedClassInterface
{
    public function __construct(
        private readonly ClassReflection $classReflection,
    ) {
    }

    public function name(): string
    {
        return $this->classReflection->getName();
    }

    public function extendsClass(string $parent): bool
    {
        foreach ($this->classReflection->getParents() as $ancestor) {
            if ($ancestor->getName() === $parent) {
                return true;
            }
        }

        return false;
    }

    public function interfaceNames(): array
    {
        $names = [];

        foreach ($this->classReflection->getInterfaces() as $interface) {
            $names[] = $interface->getName();
        }

        return $names;
    }

    public function interfaceHasMethod(string $interface, string $method): bool
    {
        foreach ($this->classReflection->getInterfaces() as $candidate) {
            if ($candidate->getName() === $interface) {
                return $candidate->hasMethod($method);
            }
        }

        return false;
    }
}
