<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\Framework\ServiceResolver\ServiceMap;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Psalm\Plugin\EventHandler\AfterClassLikeVisitInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeVisitEvent;
use Psalm\Storage\MethodStorage;
use Psalm\Type;

use function is_string;
use function ltrim;
use function strtolower;

/**
 * Types `getFacade()`/`getFactory()`/`getConfig()` from the `#[ServiceMap]`
 * attribute, the way `ServiceMapMethodsClassReflectionExtension` does for
 * PHPStan.
 *
 * Without this the attribute types nothing for Psalm, so a class declaring its
 * pillar the attribute-first way is *less* checked than one carrying a
 * `@method` docblock -- which Psalm reads natively. That gap is why the
 * docblocks could not simply be deleted when the runtime fallback was
 * deprecated.
 *
 * A pseudo-method is registered rather than a return-type provider because the
 * method does not exist: it is served by `__call` at runtime, and Psalm has to
 * be told it is callable before it can be told what it returns.
 */
final class ServiceMapPseudoMethods implements AfterClassLikeVisitInterface
{
    public static function afterClassLikeVisit(AfterClassLikeVisitEvent $event): void
    {
        $storage = $event->getStorage();

        foreach ($event->getStmt()->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                // The AST is visited before names are resolved in place, so
                // toString() yields whatever the source wrote -- "ServiceMap"
                // for an imported attribute. The resolver leaves the
                // fully-qualified name on the node instead.
                if (self::resolvedName($attribute->name) !== ServiceMap::class) {
                    continue;
                }

                $declared = self::readArguments($attribute->args);
                if ($declared === null) {
                    continue;
                }

                [$method, $className] = $declared;

                $pseudoMethod = new MethodStorage();
                $pseudoMethod->cased_name = $method;
                $pseudoMethod->is_static = false;
                $pseudoMethod->return_type = new Type\Union([new Type\Atomic\TNamedObject($className)]);

                $storage->pseudo_methods[strtolower($method)] = $pseudoMethod;
            }
        }
    }

    /**
     * Names are not rewritten in place at visit time; the resolver leaves the
     * fully-qualified form on the node as an attribute.
     */
    private static function resolvedName(Node $node): ?string
    {
        if (!$node instanceof Name) {
            return null;
        }

        /** @var mixed $resolved */
        $resolved = $node->getAttribute('resolvedName');

        return ltrim(is_string($resolved) ? $resolved : $node->toString(), '\\');
    }

    /**
     * @param array<array-key, \PhpParser\Node\Arg> $args
     *
     * @return array{string, string}|null
     */
    private static function readArguments(array $args): ?array
    {
        $method = null;
        $className = null;

        foreach ($args as $position => $arg) {
            $name = $arg->name?->toString() ?? (($position === 0) ? 'method' : 'className');

            if ($name === 'method' && $arg->value instanceof String_) {
                $method = $arg->value->value;
            }

            if ($name === 'className' && $arg->value instanceof ClassConstFetch) {
                $className = self::resolvedName($arg->value->class);
            }
        }

        if ($method === null || $className === null) {
            return null;
        }

        return [$method, $className];
    }
}
