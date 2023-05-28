<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

final class AppModule
{
    private function __construct(
        private string $moduleName,
        private string $facadeClass,
    ) {
    }

    public static function fromClass(string $facadeClass): self
    {
        $parts = explode('\\', $facadeClass);
        array_pop($parts);
        $moduleName = (string)end($parts);

        return new self(
            $moduleName,
            $facadeClass,
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
        return $this->facadeClass;
    }
}
