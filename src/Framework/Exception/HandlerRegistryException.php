<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class HandlerRegistryException extends RuntimeException
{
    public static function classDoesNotExist(string|int $key, string $className): self
    {
        return new self(sprintf(
            '"%s" is registered under key "%s" in a handler registry, and no such class exists.',
            $className,
            (string) $key,
        ) . ErrorSuggestionHelper::addHelpfulTip('class_not_found'));
    }
}
