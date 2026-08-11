<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use function ksort;
use function serialize;
use function sha1;
use function substr;

/**
 * The part of a bootstrap that decides what a class-name cache key resolves
 * to, reduced to a filename-sized hash.
 *
 * Exactly two inputs qualify: the project namespaces (ordered -- order is
 * priority, so it stays significant) and the suffix-types map (sorted --
 * declaration order changes nothing, so it must not change the hash).
 * Bindings pick constructor arguments, config values have their own cache,
 * and the environment only matters through these two, so none of them belong.
 */
final class BootstrapFingerprint
{
    /**
     * @param list<string> $projectNamespaces
     * @param array<string, list<string>|string> $suffixTypes
     */
    public static function compute(array $projectNamespaces, array $suffixTypes): string
    {
        ksort($suffixTypes);

        return substr(sha1(serialize([$projectNamespaces, $suffixTypes])), 0, 12);
    }
}
