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

    public static function noAutoloadPsr4MatchFound(string $desiredNamespace): self
    {
        return new self('No autoload psr-4 match found for ' . $desiredNamespace);
    }
}
