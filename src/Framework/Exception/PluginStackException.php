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

    public static function doesNotImplementContract(string $className, string $contract): self
    {
        return new self(sprintf(
            '"%s" is registered in the "%s" plugin stack and does not implement it.',
            $className,
            $contract,
        ));
    }
}
