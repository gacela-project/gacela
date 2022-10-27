<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

interface ClassInfoInterface
{
    public function getModuleNamespace(): string;

    public function getModuleName(): string;

    public function getResolvableType(): string;

    public function getCacheKey(): string;

    public function toString(): string;
}
