<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

final class AppModule
{
    public function __construct(
        private string $moduleName,
        private string $className,
        private string $namespace,
    ) {
    }

    public static function fromClass(string $fullyQualifiedClassName): self
    {
        $parts = explode('\\', $fullyQualifiedClassName);
        $className = array_pop($parts);
        $moduleName = (string)end($parts);
        $namespace = implode('\\', $parts);

        return new self(
            $moduleName,
            $className,
            $namespace,
        );
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }

    /**
     * @return class-string
     */
    public function facadeClass(): string
    {
        return sprintf(
            '%s\\%s',
            $this->namespace,
            $this->className,
        );
    }
}
