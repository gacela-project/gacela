<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\CommandArguments;

use RuntimeException;

final class CommandArgumentsException extends RuntimeException
{
    public static function noAutoloadFound(): self
    {
        return new self('No autoload found in your composer.json');
    }

    public static function noAutoloadPsr4Found(): self
    {
        return new self('No autoload psr-4 match found in your composer.json');
    }

    /**
     * @param list<string> $knownPsr4
     */
    public static function noAutoloadPsr4MatchFound(string $desiredNamespace, array $knownPsr4 = []): self
    {
        $parsedKnownPsr4 = array_map(static fn (string $p) => str_replace('\\', '', $p), $knownPsr4);

        return new self(
            sprintf(
                'No autoload psr-4 match found for %s. Known PSR-4: %s',
                $desiredNamespace,
                implode(', ', $parsedKnownPsr4)
            )
        );
    }
}
