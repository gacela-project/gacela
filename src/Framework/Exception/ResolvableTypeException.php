<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class ResolvableTypeException extends RuntimeException
{
    public static function emptyKind(): self
    {
        return new self('A resolvable type needs a non-empty kind, e.g. addResolvableType("Exporter").');
    }

    public static function unknownAbstractClass(string $kind, string $abstractClass): self
    {
        return new self(sprintf(
            'The "%s" kind names "%s" as its base, and no such class or interface exists.',
            $kind,
            $abstractClass,
        ));
    }

    public static function suffixAlreadyClaimed(string $suffix, string $owner, string $claimant): self
    {
        return new self(sprintf(
            'The suffix "%s" already belongs to the "%s" kind, so "%s" cannot claim it too: '
            . 'a class name ending in it would resolve by declaration order. Give "%s" a suffix of its own.',
            $suffix,
            $owner,
            $claimant,
            $claimant,
        ));
    }
}
