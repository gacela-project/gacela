<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function strlen;

final class ResolvableType
{
    private const DEFAULT_ALLOWED_TYPES = [
        'Facade',
        'Factory',
        'Config',
        'Provider',
    ];

    private function __construct(
        private readonly string $resolvableType,
        private readonly string $moduleName,
    ) {
    }

    /**
     * Split the moduleName and resolvableType from a className.
     */
    public static function fromClassName(string $className): self
    {
        foreach (self::DEFAULT_ALLOWED_TYPES as $resolvableType) {
            if (str_contains($className, $resolvableType)) {
                $moduleName = substr($className, 0, strlen($className) - strlen($resolvableType));
                return new self($resolvableType, $moduleName);
            }
        }

        $lastPos = (int)strrpos($className, '\\');
        $customResolvableType = substr($className, $lastPos);
        $moduleName = str_replace($customResolvableType, '', $className);

        return new self(ltrim($customResolvableType, '\\'), $moduleName);
    }

    public function resolvableType(): string
    {
        return $this->resolvableType;
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }
}
