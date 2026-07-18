<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\ClassResolver;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Event\ClassResolver\Cache\ClassNamePhpCacheCreatedEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameCachedFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameInvalidCandidateFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameValidCandidateFoundEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use PHPUnit\Framework\TestCase;

final class EventGettersTest extends TestCase
{
    public function test_class_name_cached_found_event_exposes_cache_key_and_class_name(): void
    {
        $event = new ClassNameCachedFoundEvent('the-cache-key', 'Some\CachedClass');

        self::assertSame('the-cache-key', $event->cacheKey());
        self::assertSame('Some\CachedClass', $event->className());
    }

    public function test_class_name_not_found_event_exposes_class_info_and_resolvable_types(): void
    {
        $classInfo = ClassInfo::from(self::class, 'Facade');
        $event = new ClassNameNotFoundEvent($classInfo, ['Facade', 'Factory']);

        self::assertSame($classInfo, $event->classInfo());
        self::assertSame(['Facade', 'Factory'], $event->resolvableTypes());
    }

    public function test_class_name_php_cache_created_event_exposes_cache_dir(): void
    {
        $event = new ClassNamePhpCacheCreatedEvent('/tmp/gacela-cache-dir');

        self::assertSame('/tmp/gacela-cache-dir', $event->cacheDir());
    }

    public function test_invalid_candidate_event_exposes_class_name(): void
    {
        $event = new ClassNameInvalidCandidateFoundEvent('Some\InvalidCandidate');

        self::assertSame('Some\InvalidCandidate', $event->className());
    }

    public function test_valid_candidate_event_exposes_class_name(): void
    {
        $event = new ClassNameValidCandidateFoundEvent('Some\ValidCandidate');

        self::assertSame('Some\ValidCandidate', $event->className());
    }

    public function test_resolver_event_exposes_class_info(): void
    {
        $classInfo = ClassInfo::from(self::class, 'Factory');
        $event = new ResolvedClassCreatedEvent($classInfo);

        self::assertSame($classInfo, $event->classInfo());
    }
}
