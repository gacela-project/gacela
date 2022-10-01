<?php

declare(strict_types=1);

namespace Gacela\Console\Domain;

use LogicException;

final class ConsoleException extends LogicException
{
    public static function composerJsonNotFound(): self
    {
        return new self('composer.json file not found but it is required.');
    }
}
