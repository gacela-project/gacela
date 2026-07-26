<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use RuntimeException;

use function sprintf;

final class MalformedCycleAllowListException extends RuntimeException
{
    public static function entryIsNotAnObject(int $position): self
    {
        return new self(sprintf('Allowed-cycle entry #%d must be an object with "modules" and "reason".', $position));
    }

    public static function missingModules(int $position): self
    {
        return new self(sprintf('Allowed-cycle entry #%d must list at least two "modules".', $position));
    }

    public static function missingReason(int $position): self
    {
        return new self(sprintf(
            'Allowed-cycle entry #%d needs a non-empty "reason": an allowance nobody justified is indistinguishable from a cycle nobody noticed.',
            $position,
        ));
    }
}
