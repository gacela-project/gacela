<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\CommandArguments;

use function strrpos;
use function substr;

final class CommandArguments
{
    public function __construct(
        private readonly string $namespace,
        private readonly string $directory,
    ) {
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * The module's name, which prefixes its class names: the last segment of
     * its namespace. Not the directory's: a psr-4 root such as
     * `modules/billing-core/` would make `billing-coreFacade`, which no
     * class can be called.
     */
    public function basename(): string
    {
        $position = strrpos($this->namespace, '\\');

        return $position === false ? $this->namespace : substr($this->namespace, $position + 1);
    }
}
