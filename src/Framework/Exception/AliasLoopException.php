<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function implode;

final class AliasLoopException extends RuntimeException
{
    /**
     * @param list<string> $chain
     */
    public static function detected(array $chain): self
    {
        return new self('Alias loop: ' . implode(' -> ', $chain) . '. Configure aliases to end at a service id.');
    }
}
