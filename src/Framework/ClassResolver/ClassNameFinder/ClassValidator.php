<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

final class ClassValidator implements ClassValidatorInterface
{
    /** @var array<string,bool> */
    private static array $existsCache = [];

    public function isClassNameValid(string $className): bool
    {
        return self::$existsCache[$className] ?? (self::$existsCache[$className] = class_exists($className));
    }

    /**
     * Drops the memoized "this class does not exist" answers.
     *
     * A class that exists cannot stop existing within a process, so a positive
     * answer never goes stale and is kept. Clearing it too would re-run the
     * autoloader for every class on the next cold resolution: measured at
     * +20.07% on `FileCacheBench::bench_without_cache`, for no correctness gain.
     *
     * Only the negative answers can become wrong, which happens when a class
     * becomes loadable after it was first asked about.
     */
    public static function resetCache(): void
    {
        self::$existsCache = array_filter(self::$existsCache);
    }
}
