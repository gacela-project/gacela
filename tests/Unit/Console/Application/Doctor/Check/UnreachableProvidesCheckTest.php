<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\UnreachableProvidesCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\HiddenProvidesProvider;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\StubFacade;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\UniqueIdProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * `#[Provides]` on a method the scanner never looks at.
 *
 * `ProvidesScanner::entriesFor()` reads `getMethods(IS_PUBLIC)`, so the
 * attribute on a private or protected method is not there as far as the
 * container is concerned. Verified before this check existed: of four declared
 * ids on one Provider, only the public and the public-static ones came back.
 *
 * The failure compounds -- `getProvidedDependency()` answers `null` for the id
 * that never registered, which is itself silent, so the first sign is a call on
 * null somewhere else.
 */
final class UnreachableProvidesCheckTest extends TestCase
{
    public function test_a_provider_whose_declarations_are_all_public_passes(): void
    {
        $result = (new UnreachableProvidesCheck([$this->module(UniqueIdProvider::class)]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 provider(s) checked'], $result->details);
    }

    public function test_a_private_declaration_is_reported(): void
    {
        $result = (new UnreachableProvidesCheck([$this->module(HiddenProvidesProvider::class)]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString(HiddenProvidesProvider::HIDDEN, $this->detailMentioning($result->details, 'hidden()'));
        self::assertStringContainsString('which is private', $this->detailMentioning($result->details, '::hidden()'));
    }

    /**
     * Both, not just the first one found: a Provider with two mistakes gets two
     * lines, or fixing the one reported uncovers the next.
     *
     * Searched rather than indexed. Nothing promises which method reflection
     * hands back first, and the formatter has already reordered these two once.
     */
    public function test_a_protected_declaration_is_reported_alongside_it(): void
    {
        $result = (new UnreachableProvidesCheck([$this->module(HiddenProvidesProvider::class)]))->run();

        self::assertCount(2, $result->details);
        self::assertStringContainsString('which is protected', $this->detailMentioning($result->details, 'alsoHidden()'));
    }

    public function test_the_public_declaration_beside_them_is_not_reported(): void
    {
        $result = (new UnreachableProvidesCheck([$this->module(HiddenProvidesProvider::class)]))->run();

        foreach ($result->details as $detail) {
            self::assertStringNotContainsString(HiddenProvidesProvider::VISIBLE, $detail);
        }
    }

    /**
     * The module with no Provider comes first, so skipping it has to continue
     * rather than stop -- were it last, abandoning the loop there would look
     * exactly like skipping it.
     */
    public function test_a_module_without_a_provider_is_skipped_and_the_next_one_still_read(): void
    {
        $result = (new UnreachableProvidesCheck([
            $this->module(null),
            $this->module(HiddenProvidesProvider::class),
        ]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertCount(2, $result->details);
    }

    /**
     * @param list<string> $details
     */
    private function detailMentioning(array $details, string $needle): string
    {
        foreach ($details as $detail) {
            if (str_contains($detail, $needle)) {
                return $detail;
            }
        }

        self::fail(sprintf('no reported detail mentions "%s"', $needle));
    }

    /**
     * @param class-string|null $providerClass
     */
    private function module(?string $providerClass): AppModule
    {
        return new AppModule(
            'App\TestModule',
            'TestModule',
            StubFacade::class,
            null,
            null,
            $providerClass,
        );
    }
}
