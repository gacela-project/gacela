<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Infrastructure;

use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;

/**
 * Counts how much class resolution one request costs.
 *
 * Registered in `gacela.php` against `AbstractGacelaClassResolverEvent`, which
 * the dispatcher matches by inheritance: one registration covers every resolver
 * event there is, and the callable stays typed against the parent so static
 * analysis can check it.
 *
 * Process-wide, because the resolver is. `reset()` is what a test calls between
 * runs.
 */
final class ResolverActivityLog
{
    /** @var list<string> */
    private static array $entries = [];

    public static function record(AbstractGacelaClassResolverEvent $event): void
    {
        self::$entries[] = $event->toString();
    }

    /**
     * @return list<string>
     */
    public static function entries(): array
    {
        return self::$entries;
    }

    public static function reset(): void
    {
        self::$entries = [];
    }
}
