<?php

declare(strict_types=1);

namespace Gacela\ClassResolver;

use RuntimeException;

final class ClassInfo
{
    private const KEY_MODULE_NAME = 1;

    private ?string $cacheKey = null;
    private string $callerModuleName = '';

    /**
     * @param object|string $callerClass
     */
    public function setClass($callerClass): self
    {
        if (is_object($callerClass)) {
            $callerClass = get_class($callerClass);
        }

        $callerClassParts = [self::KEY_MODULE_NAME => $callerClass];
        if ($this->isFullyQualifiedClassName($callerClass)) {
            $callerClassParts = explode('\\', ltrim($callerClass, '\\'));
        }

        $this->callerModuleName = $callerClassParts[self::KEY_MODULE_NAME];

        return $this;
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

    /**
     * @throws \RuntimeException
     */
    public function getModule(): string
    {
        if (empty($this->callerModuleName)) {
            throw new RuntimeException('Could not extract a module name which is mandatory for the resolver to work!');
        }
        return $this->callerModuleName;
    }
}
