<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis;

use Gacela\StaticAnalysis\NamespaceMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NamespaceMatchTest extends TestCase
{
    #[DataProvider('namespaceSpellings')]
    public function test_namespace_matching_keeps_segment_boundaries(string $namespace): void
    {
        self::assertTrue(NamespaceMatch::covers($namespace, '\\APP\\Shared\\'));
        self::assertTrue(NamespaceMatch::covers($namespace, 'App\\Shared\\Clock'));
        self::assertFalse(NamespaceMatch::covers($namespace, 'App\\SharedFoo\\Clock'));
        self::assertFalse(NamespaceMatch::covers($namespace, 'Other\\Shared'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namespaceSpellings(): iterable
    {
        yield 'canonical' => ['App\\Shared'];
        yield 'leading separator' => ['\\App\\Shared'];
        yield 'trailing separator' => ['App\\Shared\\'];
        yield 'other case' => ['app\\shared'];
    }
}
