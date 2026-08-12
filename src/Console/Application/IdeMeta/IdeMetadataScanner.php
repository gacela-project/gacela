<?php

declare(strict_types=1);

namespace Gacela\Console\Application\IdeMeta;

use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\IdeMeta\ProvidedDependencyMap;
use Gacela\Framework\Attribute\ProvidesScanner;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function class_exists;
use function count;
use function interface_exists;

/**
 * Reads the type of every string-keyed service an application provides.
 *
 * Nothing is instantiated: a Provider is asked through reflection, so scanning
 * cannot run the wiring it describes.
 */
final class IdeMetadataScanner
{
    /**
     * @param list<AppModule> $modules
     */
    public function scan(array $modules): ProvidedDependencyMap
    {
        // Keyed by class name as well as held as the value, so that repeated
        // claims of one type collapse to one entry without counting.
        /** @var array<string, array<class-string, class-string>> $claims */
        $claims = [];

        foreach ($modules as $module) {
            $providerClass = $module->providerClass();

            // A module without a Provider contributes nothing, and the modules
            // after it still do.
            if ($providerClass === null) {
                continue;
            }

            foreach ($this->providedTypes($providerClass) as $id => $className) {
                $claims[$id][$className] = $className;
            }
        }

        $entries = [];
        $ambiguous = [];

        foreach ($claims as $id => $classNames) {
            $candidates = array_values($classNames);

            // Two providers agreeing on the type is not a conflict, however many
            // register the id; only disagreement leaves nothing to write.
            if (count($candidates) === 1) {
                $entries[$id] = $candidates[0];
                continue;
            }

            sort($candidates);
            $ambiguous[$id] = $candidates;
        }

        return new ProvidedDependencyMap($entries, $ambiguous);
    }

    /**
     * @param class-string $providerClass
     *
     * @return array<string, class-string>
     */
    private function providedTypes(string $providerClass): array
    {
        $types = [];

        foreach (ProvidesScanner::entriesFor(new ReflectionClass($providerClass)) as $entry) {
            $id = $entry['id'];
            // An id that already names a class is answered by the wildcard the
            // renderer always writes, the same way both analysers answer it.
            // Writing the #[Provides] return type over it would replace the
            // declared contract with today's implementation of it.
            if (class_exists($id)) {
                continue;
            }

            if (interface_exists($id)) {
                continue;
            }

            $className = $this->returnedClass($entry['method']);

            if ($className !== null) {
                $types[$id] = $className;
            }
        }

        return $types;
    }

    /**
     * The class a `#[Provides]` method declares it returns, when the editor map
     * can express it.
     *
     * A map value is a class name and nothing else, so `array`, `?Foo` and
     * unions have no representation there. They are skipped rather than
     * approximated: `Foo` for a method returning `?Foo` would hide exactly the
     * null the caller has to handle.
     *
     * @return class-string|null
     */
    private function returnedClass(ReflectionMethod $method): ?string
    {
        $returnType = $method->getReturnType();

        if (!$returnType instanceof ReflectionNamedType || $returnType->isBuiltin() || $returnType->allowsNull()) {
            return null;
        }

        $name = $this->resolveRelativeType($returnType->getName(), $method->getDeclaringClass());

        return class_exists($name) || interface_exists($name) ? $name : null;
    }

    /**
     * `self`, `static` and `parent` named as the class each stands for.
     *
     * PHP 8.5 resolves a relative return type in `getName()` and earlier
     * versions hand back the literal word, so without this the same Provider
     * yields a different file per PHP version -- and because the doctor check
     * compares content, a team split across versions would see it flip between
     * "matches" and "stale" depending on who ran the command. Resolving here
     * makes every supported version agree, and agrees with 8.5 rather than
     * against it.
     *
     * `static` is the one 8.5 does *not* resolve, so it is handled here on
     * every version. It resolves to the declaring class, an upper bound rather
     * than the exact runtime class -- still a true statement about the value.
     *
     * Lower-cased because 8.4 hands back the casing the source used: a return
     * type written `SELF` arrives as `'SELF'` there and as the resolved class
     * on 8.5, which is the divergence this method exists to remove.
     *
     * @param ReflectionClass<object> $declaringClass
     */
    private function resolveRelativeType(string $name, ReflectionClass $declaringClass): string
    {
        $parent = $declaringClass->getParentClass();

        return match (strtolower($name)) {
            'self', 'static' => $declaringClass->getName(),
            // A class declaring `: parent` always has one; falling back to the
            // literal keeps this total, and it resolves to no class anyway.
            'parent' => $parent === false ? $name : $parent->getName(),
            default => $name,
        };
    }
}
