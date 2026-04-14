<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

final class ClassValidator implements ClassValidatorInterface
{
    /** @var array<string,bool> */
    private static array $existsCache = [];

    public function isClassNameValid(string $className): bool
    {
        if (isset(self::$existsCache[$className])) {
            return self::$existsCache[$className];
        }

        return self::$existsCache[$className] = class_exists($className);
    }

    public static function resetCache(): void
    {
        self::$existsCache = [];
    }
}
