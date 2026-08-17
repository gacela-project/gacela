<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

/**
 * Counts its own construction, which is how a test can tell "reachable from
 * Gacela" from "built because Gacela was configured".
 *
 * The name says who built it. Object identity would say the same thing only as
 * long as every container in the matrix memoises a bound closure the same way,
 * and they do not: a constructor argument only Symfony supplies is the fact
 * itself rather than a consequence of it.
 */
final class CountingService implements ServiceContract
{
    public const AUTOWIRED = 'autowired';

    public const FROM_SYMFONY = 'symfony';

    public static int $constructed = 0;

    public function __construct(
        private readonly string $name = self::AUTOWIRED,
    ) {
        ++self::$constructed;
    }

    public function name(): string
    {
        return $this->name;
    }
}
