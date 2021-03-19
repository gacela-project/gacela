<?php

declare(strict_types=1);

namespace Gacela\ClassResolver;

use RuntimeException;

final class ClassInfo
{
    private ?string $cacheKey = null;
    private string $callerModuleName = '';
    private string $callerNamespace = '';

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
            $this->cacheKey = $this->buildCacheKey($resolvableType);
        }

        return $this->cacheKey;
    }

    private function buildCacheKey(string $resolvableType): string
    {
        return $this->getModule() . $resolvableType;
    }

    private function isFullyQualifiedClassName(string $callerClass): bool
    {
        return (strpos($callerClass, '\\') !== false);
    }

    public function getModule(): string
    {
        if (empty($this->callerModuleName)) {
            throw new RuntimeException('Could not extract a module name which is mandatory for the resolver to work!');
        }
        return $this->callerModuleName;
    }

    public function getFullNamespace(): string
    {
        if (empty($this->callerNamespace)) {
            throw new RuntimeException('Could not extract the namespace which is mandatory for the resolver to work!');
        }
        return $this->callerNamespace;
    }
}
