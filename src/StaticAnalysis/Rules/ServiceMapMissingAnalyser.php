<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\Framework\ServiceResolverAwareTrait;
use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ResolvedName;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Arg;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TraitUse;

use function in_array;
use function preg_match_all;

use function sprintf;

use const PREG_SET_ORDER;

/**
 * A pillar accessor documented with `@method` and not declared with
 * `#[ServiceMap]`.
 *
 * `ServiceResolverAwareTrait::__call()` resolves the accessor by reading the
 * class's own source at runtime -- the `@method` tag, then the file's `use`
 * statements. Both are deprecated in 2.0 and removed in 3.0, and the class goes
 * on working until it stops.
 *
 * ```php
 *
 * /** @method WalletFacade getFacade() *\/
 * final class WalletCommand { use ServiceResolverAwareTrait; }
 * ```
 *
 * The runtime says so too, but only for accessors a run actually reaches, and
 * only on a **cold** resolve -- the answer is memoized per caller-and-method,
 * so a warm cache is silent. A migration driven by notices is therefore a
 * migration over the code paths the test suite happens to execute. This is the
 * same fact, read from the source, for every class at once.
 *
 * `UPGRADE.md` states the gap this closes: PHPStan reads `@method` natively, so
 * a class carrying one is a class it considers correct, with or without the
 * attribute. A green analysis run otherwise says nothing about 3.0 readiness.
 *
 * Deliberately narrow, because it runs inside a consumer's build:
 *
 * - Only classes using {@see ServiceResolverAwareTrait} **directly**. A parent
 *   using it is invisible in one file's AST, and guessing would report classes
 *   that are not Gacela's at all.
 * - Only a `@method` naming a method the class does not declare. A real method
 *   never reaches `__call()`, so its docblock is documentation, not resolution.
 * - Not `@extends AbstractFacade<X>`. That form is read by `FactoryResolver`
 *   through the module's naming convention, which is not going anywhere; the
 *   docblock only types it.
 */
final class ServiceMapMissingAnalyser implements ClassAnalyserInterface
{
    private const ATTRIBUTE = 'ServiceMap';

    /**
     * `@method [static] <type> <name>`. A tag written without a return type
     * does not match, and is left alone: nothing can be suggested for it.
     *
     * The parameter list is not required, because the resolver does not require
     * it either -- `DocBlockParser` matches the name at a word boundary, so a
     * tag written without one still resolves and so must still be reported.
     */
    private const METHOD_TAG = '#@method\s+(?:static\s+)?(?P<type>[^\s(]+)\s+(?P<name>\w+)#';

    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        if (!$this->usesTheResolverTrait($node)) {
            return [];
        }

        // No early return for a class without a docblock: there is nothing to
        // match in an empty string, so the loop below is already the answer.
        $docBlock = $node->getDocComment()?->getText() ?? '';

        $declared = $this->accessorsDeclaredByAttribute($node);
        $violations = [];

        foreach ($this->accessorsDocumented($docBlock) as $method => $type) {
            if (in_array($method, $declared, true)) {
                continue;
            }

            if ($node->getMethod($method) instanceof ClassMethod) {
                continue;
            }

            $violations[] = new Violation(
                sprintf(
                    '%s::%s() is resolved from its @method docblock, which is deprecated and removed in 3.0',
                    $class->name(),
                    $method,
                ),
                'gacela.serviceMapMissing',
                sprintf(
                    "Declare it with #[ServiceMap(method: '%s', className: %s::class)].",
                    $method,
                    $type,
                ),
            );
        }

        return $violations;
    }

    private function usesTheResolverTrait(ClassLike $node): bool
    {
        foreach ($node->stmts as $statement) {
            if (!$statement instanceof TraitUse) {
                continue;
            }

            foreach ($statement->traits as $trait) {
                if (ResolvedName::of($trait) === ServiceResolverAwareTrait::class) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The type is reported as the docblock spells it, unqualified or not: the
     * suggested attribute goes into that same file, where the import that makes
     * the short name resolve is already the reason the fallback worked.
     *
     * @return array<string, string> method name => type as written
     */
    private function accessorsDocumented(string $docBlock): array
    {
        preg_match_all(self::METHOD_TAG, $docBlock, $matches, PREG_SET_ORDER);

        $accessors = [];

        foreach ($matches as $match) {
            $accessors[$match['name']] = $match['type'];
        }

        return $accessors;
    }

    /**
     * Attributes are read off this class only, never its parents -- which is
     * what the runtime does, because PHP does not inherit attributes.
     *
     * @return list<string>
     */
    private function accessorsDeclaredByAttribute(ClassLike $node): array
    {
        $declared = [];

        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (ShortName::of(ResolvedName::of($attribute->name)) !== self::ATTRIBUTE) {
                    continue;
                }

                $method = $this->methodArgument($attribute->args);

                if ($method !== null) {
                    $declared[] = $method;
                }
            }
        }

        return $declared;
    }

    /**
     * `method` is the attribute's first parameter, so both spellings reach it:
     * `#[ServiceMap('getFacade', X::class)]` and
     * `#[ServiceMap(method: 'getFacade', className: X::class)]`.
     *
     * @param list<Arg> $args
     */
    private function methodArgument(array $args): ?string
    {
        foreach ($args as $position => $arg) {
            // Named, it is whichever argument says `method:` -- which need not
            // be written first. Unnamed, it is the first one, and only that one.
            $isMethod = $arg->name instanceof Identifier
                ? $arg->name->toString() === 'method'
                : $position === 0;

            if ($isMethod && $arg->value instanceof String_) {
                return $arg->value->value;
            }
        }

        return null;
    }
}
