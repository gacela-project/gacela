<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis;

use Gacela\StaticAnalysis\ResolvedName;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\TestCase;

/**
 * Each case here is a shape some real resolver produces. Getting this wrong is
 * silent: a short name simply belongs to no module, so the rule reports nothing
 * and reads as a pass.
 */
final class ResolvedNameTest extends TestCase
{
    private const FQCN = 'App\Modules\Billing\Domain\InvoiceRepository';

    /**
     * PHPStan rewrites names in place, so there is nothing to look up.
     */
    public function test_a_name_rewritten_in_place_is_used_as_written(): void
    {
        self::assertSame(self::FQCN, ResolvedName::of(new FullyQualified(self::FQCN)));
    }

    /**
     * Psalm writes the attribute as a string.
     */
    public function test_a_string_attribute_wins_over_the_source_text(): void
    {
        $name = new Name('InvoiceRepository');
        $name->setAttribute('resolvedName', self::FQCN);

        self::assertSame(self::FQCN, ResolvedName::of($name));
    }

    /**
     * php-parser's own NameResolver writes it as a node.
     */
    public function test_a_name_attribute_wins_over_the_source_text(): void
    {
        $name = new Name('InvoiceRepository');
        $name->setAttribute('resolvedName', new FullyQualified(self::FQCN));

        self::assertSame(self::FQCN, ResolvedName::of($name));
    }

    public function test_an_unresolved_name_falls_back_to_the_source_text(): void
    {
        self::assertSame('InvoiceRepository', ResolvedName::of(new Name('InvoiceRepository')));
    }

    /**
     * An attribute of some other type is not a name, and reading it as one would
     * be worse than falling back.
     */
    public function test_an_attribute_that_is_neither_falls_back(): void
    {
        $name = new Name('InvoiceRepository');
        $name->setAttribute('resolvedName', 42);

        self::assertSame('InvoiceRepository', ResolvedName::of($name));
    }

    /**
     * A leading separator is legal and means the same class. Module names carry
     * none, so keeping it would make every such name miss its module.
     */
    public function test_a_leading_separator_is_dropped(): void
    {
        self::assertSame(self::FQCN, ResolvedName::of(new Name('\\' . self::FQCN)));
    }

    public function test_a_leading_separator_is_dropped_from_the_attribute_too(): void
    {
        $name = new Name('InvoiceRepository');
        $name->setAttribute('resolvedName', '\\' . self::FQCN);

        self::assertSame(self::FQCN, ResolvedName::of($name));
    }
}
