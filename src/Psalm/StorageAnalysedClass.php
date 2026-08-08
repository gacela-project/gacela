<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Psalm\Codebase;
use Psalm\Storage\ClassLikeStorage;

use function array_values;
use function strtolower;

/**
 * The {@see AnalysedClassInterface} seam, answered from Psalm's class storage.
 *
 * The PHPStan counterpart is `Gacela\PHPStan\Rules\ReflectionAnalysedClass`;
 * between the two of them they are the entire host-specific half of the
 * architecture rules.
 *
 * @internal
 */
final class StorageAnalysedClass implements AnalysedClassInterface
{
    public function __construct(
        private readonly ClassLikeStorage $storage,
        private readonly Codebase $codebase,
    ) {
    }

    public function name(): string
    {
        return $this->storage->name;
    }

    public function extendsClass(string $parent): bool
    {
        // Keyed by lowercase name, and it holds the whole ancestry rather than
        // the immediate parent -- which is what the rules ask about.
        return isset($this->storage->parent_classes[strtolower($parent)]);
    }

    public function interfaceNames(): array
    {
        return array_values($this->storage->class_implements);
    }

    public function interfaceHasMethod(string $interface, string $method): bool
    {
        return $this->codebase->methodExists($interface . '::' . $method);
    }
}
