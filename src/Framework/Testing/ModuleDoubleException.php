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

    /**
     * The double is registered under an id the application resolves by type, so
     * a consumer receives it where it asked for the real class. An object of
     * another type gets there and fails at the call site, naming neither the
     * test that registered it nor the id it was registered under.
     */
    public static function notAnInstanceOf(string $id, object $double): self
    {
        return new self(sprintf(
            'The double for "%s" is a %s, which is not an instance of it. '
            . 'A double registered under a class or interface is handed to whoever asks for that type, '
            . 'so it has to be one -- a stub of it, a subclass, or another implementation.',
            $id,
            $double::class,
        ));
    }
}
