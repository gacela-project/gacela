<?php

declare(strict_types=1);

namespace Gacela\Framework\Testing;

use Gacela\Framework\AbstractFacade;
use RuntimeException;

use function class_exists;
use function sprintf;

final class ModuleDoubleException extends RuntimeException
{
    public static function notAFacade(string $className): self
    {
        return new self(sprintf(
            class_exists($className)
                ? '"%s" is not a %s. Name the module by the Facade its consumers use: that is the class the resolver derives the module\'s pillars from.'
                : 'There is no class "%s". Name the module by the Facade its consumers use, so the double replaces the pillar that Facade resolves. (%s)',
            $className,
            AbstractFacade::class,
        ));
    }
}
