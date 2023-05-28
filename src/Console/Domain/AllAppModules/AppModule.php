<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

final class AppModule
{
    public function __construct(
        private string $className,
        private string $namespace,
    ) {
    }

    public function className(): string
    {
        return $this->className;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return class-string
     */
    public function fullyQualifiedClassName(): string
    {
        return sprintf(
            '%s\\%s',
            $this->namespace,
            $this->className,
        );
    }
}
