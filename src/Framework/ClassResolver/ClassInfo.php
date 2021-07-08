<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class ClassInfo
{
    private ?string $cacheKey = null;
    private string $callerModuleName;
    private string $callerNamespace;

    public function __construct(object $callerObject)
    {
        $callerClass = get_class($callerObject);
        $callerClassParts = explode('\\', ltrim($callerClass, '\\'));

        $this->callerNamespace = implode('\\', array_slice($callerClassParts, 0, count($callerClassParts) - 1));
        $this->callerModuleName = $callerClassParts[count($callerClassParts) - 2];
    }

    public function getCacheKey(string $resolvableType): string
    {
        if (!$this->cacheKey) {
            $this->cacheKey = $this->getFullNamespace() . $this->getModule() . $resolvableType;
        }

        return $this->cacheKey;
    }

    public function getModule(): string
    {
        return $this->callerModuleName;
    }

    public function getFullNamespace(): string
    {
        return $this->callerNamespace;
    }
}
