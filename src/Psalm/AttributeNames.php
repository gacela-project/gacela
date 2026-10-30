<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use PhpParser\Node\Attribute;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use Psalm\Aliases;
use Psalm\Type;

final class AttributeNames
{
    public static function resolveIn(ClassLike $node, Aliases $aliases): void
    {
        foreach ((new NodeFinder())->findInstanceOf($node, Attribute::class) as $attribute) {
            $attribute->name->setAttribute('resolvedName', self::resolve($attribute->name, $aliases));
        }
    }

    private static function resolve(Name $name, Aliases $aliases): string
    {
        if ($name->isFullyQualified()) {
            return $name->toString();
        }

        if ($name->isRelative()) {
            return ($aliases->namespace === null ? '' : $aliases->namespace . '\\') . $name->toString();
        }

        return Type::getFQCLNFromString($name->toString(), $aliases);
    }
}
