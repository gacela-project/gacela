<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\CommandArguments;

use Gacela\Console\Domain\CommandArguments\ModulePath;
use PHPUnit\Framework\TestCase;

final class ModulePathTest extends TestCase
{
    public function test_a_plain_module_path_is_usable(): void
    {
        self::assertNull(ModulePath::firstUnusableSegment('App/Blog'));
    }

    public function test_a_deep_path_is_usable(): void
    {
        self::assertNull(ModulePath::firstUnusableSegment('App/Modules/Billing/Invoices'));
    }

    /**
     * The one people actually hit: hyphenated directories are a habit from
     * ecosystems where that is the convention, and `class user-profileFacade`
     * is a parse error.
     */
    public function test_a_hyphenated_segment_is_not_usable(): void
    {
        self::assertSame('user-profile', ModulePath::firstUnusableSegment('App/user-profile'));
    }

    public function test_a_segment_starting_with_a_digit_is_not_usable(): void
    {
        self::assertSame('2fa', ModulePath::firstUnusableSegment('App/2fa'));
    }

    public function test_a_segment_with_a_space_is_not_usable(): void
    {
        self::assertSame('My Module', ModulePath::firstUnusableSegment('App/My Module'));
    }

    public function test_an_empty_segment_is_not_usable(): void
    {
        self::assertSame('', ModulePath::firstUnusableSegment('App//Blog'));
    }

    /**
     * The first one, so the message names what to fix rather than the last
     * thing that happened to fail.
     */
    public function test_the_first_unusable_segment_is_the_one_reported(): void
    {
        self::assertSame('a-b', ModulePath::firstUnusableSegment('App/a-b/c-d'));
    }

    /**
     * Accented and non-latin names are valid PHP identifiers, and refusing them
     * would be this check inventing a rule PHP does not have.
     */
    public function test_a_non_ascii_segment_is_usable(): void
    {
        self::assertNull(ModulePath::firstUnusableSegment('App/Órdenes'));
    }

    public function test_underscores_are_usable(): void
    {
        self::assertNull(ModulePath::firstUnusableSegment('App/_Internal/user_profile'));
    }

    /**
     * A leading or trailing slash is how a path gets typed, not a segment that
     * failed.
     */
    public function test_surrounding_slashes_are_ignored(): void
    {
        self::assertNull(ModulePath::firstUnusableSegment('/App/Blog/'));
    }
}
