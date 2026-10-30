<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use Gacela\Framework\Attribute\Cacheable;
use PhpParser\Node\Attribute;

use function strcasecmp;

final class CacheableAttribute
{
    public static function isOn(Attribute $attribute): bool
    {
        $name = $attribute->name;
        $resolved = ResolvedName::of($name);
        if (strcasecmp($resolved, Cacheable::class) === 0) {
            return true;
        }

        return $name->isUnqualified()
            && $name->getAttribute('resolvedName') === null
            && strcasecmp($resolved, 'Cacheable') === 0;
    }
}
