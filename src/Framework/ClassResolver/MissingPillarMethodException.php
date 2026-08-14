<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use RuntimeException;

use function sprintf;

/**
 * A method called on the stand-in a module gets when it declares no pillar of
 * that kind.
 *
 * `Factory` and `Config` resolve to an empty anonymous subclass when the module
 * has none, so a module that never touches them still works. The cost is that
 * the first call to one lands on that stand-in, and PHP reports
 * `Call to undefined method Gacela\Framework\AbstractFactory@anonymous::createThing()`
 * -- which names neither the module nor the file that is missing, and reads
 * like a framework fault rather than a module without a Factory.
 *
 * `Provider` has said this properly since it has no stand-in to fall back to.
 * This is the same answer for the two kinds that do.
 */
final class MissingPillarMethodException extends RuntimeException
{
    /**
     * @param object|class-string|null $caller the class that asked, when known, so the
     *                                         file already in its directory can be named
     */
    public static function onDefault(
        string $kind,
        string $moduleName,
        string $method,
        string $expectedClass,
        object|string|null $caller = null,
    ): self {
        $message = sprintf(
            "Module `%s` has no `%s`, so `%s()` has nowhere to be defined.\n"
            . 'Add `%s` (or check its filename matches its class name -- `gacela doctor` reports that too).',
            $moduleName,
            $kind,
            $method,
            $expectedClass,
        );

        return new self($message . self::hintFor($caller, $kind, $expectedClass));
    }

    /**
     * "Add `WalletFactory`" is the wrong instruction whenever the file is
     * written and misnamed, which is the common way a module ends up on the
     * stand-in. The same hint the resolver exceptions carry answers it here.
     *
     * @param object|class-string|null $caller
     */
    private static function hintFor(object|string|null $caller, string $kind, string $expectedClass): string
    {
        if ($caller === null) {
            return '';
        }

        $hints = ModuleDirectoryHint::findNear($caller, $kind, [$expectedClass]);
        if ($hints === []) {
            return '';
        }

        return "\nFound in the module directory:\n  - " . implode("\n  - ", $hints);
    }
}
