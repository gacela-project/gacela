<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function array_slice;
use function count;
use function get_class;

final class ClassInfo
{
    private ?string $cacheKey = null;
    private string $callerModuleName;
    private string $callerNamespace;

    public function __construct(object $callerObject)
    {
        $callerClass = get_class($callerObject);

        /** @var string[] $callerClassParts */
        $callerClassParts = explode('\\', ltrim($callerClass, '\\'));
        if (count($callerClassParts) <= 1) {
            $callerClassParts = [
                'module-name@anonymous',
                'class-name@anonymous',
            ];
        }

        $this->callerNamespace = implode('\\', array_slice($callerClassParts, 0, count($callerClassParts) - 1));
        $this->callerModuleName = $callerClassParts[count($callerClassParts) - 2] ?? '';
    }

    public function getCacheKey(string $resolvableType): string
    {
        if (!$this->cacheKey) {
            $this->cacheKey = sprintf(
                '\\%s\\%s',
                $this->getFullNamespace(),
                $resolvableType
            );
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
