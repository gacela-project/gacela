<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use function strlen;

final class ResolvableType
{
    private const ALLOWED_TYPES = [
        'Facade',
        'Factory',
        'Config',
        'DependencyProvider',
    ];

    private string $resolvableType;
    private string $moduleName;

    /**
     * Split the moduleName and resolvableType from a className.
     */
    public static function fromClassName(string $className): self
    {
        foreach (self::ALLOWED_TYPES as $resolvableType) {
            if (false !== strpos($className, $resolvableType)) {
                return new self(
                    $resolvableType,
                    substr($className, 0, strlen($className) - strlen($resolvableType))
                );
            }
        }

        return new self('', $className);
    }

    private function __construct(
        string $resolvableType,
        string $moduleName
    ) {
        $this->resolvableType = $resolvableType;
        $this->moduleName = $moduleName;
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
