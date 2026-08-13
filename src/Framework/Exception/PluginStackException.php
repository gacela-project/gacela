<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class PluginStackException extends RuntimeException
{
    public static function notDeclared(string $contract): self
    {
        return new self(sprintf(
            'No plugin stack is declared for "%s". Declare one in gacela.php with '
            . 'addPluginStack(%s::class, [...]).',
            $contract,
            $contract,
        ));
    }

    /**
     * A class name in `gacela.php` is a string until something loads it, and
     * the container answers `null` for one that resolves to nothing -- which
     * the contract check below then reported as "does not implement it",
     * sending the reader to inspect an `implements` clause on a file that is
     * not there. A typo in a plugin's class name is the ordinary way to arrive
     * here, so it carries the tips for a class that does not exist.
     */
    public static function classDoesNotExist(string $className, string $contract): self
    {
        return new self(sprintf(
            '"%s" is registered in the "%s" plugin stack, and no such class exists.',
            $className,
            $contract,
        ) . ErrorSuggestionHelper::addHelpfulTip('class_not_found'));
    }

    public static function doesNotImplementContract(string $className, string $contract): self
    {
        return new self(sprintf(
            '"%s" is registered in the "%s" plugin stack and does not implement it.',
            $className,
            $contract,
        ));
    }
}
