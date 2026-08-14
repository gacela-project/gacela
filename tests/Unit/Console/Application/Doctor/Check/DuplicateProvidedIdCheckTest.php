<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\DuplicateProvidedIdCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\RepeatedIdProvider;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\StubFacade;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\UniqueIdProvider;
use PHPUnit\Framework\TestCase;

/**
 * One `#[Provides]` id declared twice on one Provider.
 *
 * `ProvidesScanner::scan()` walks the methods in order and `set()`s each id, so
 * the last wins and the earlier method is dead. Verified against a real
 * bootstrap before this check existed: a Provider declaring `THE_THING` from
 * `first()` and again from `second()` resolves to `'SECOND'`, with no error and
 * nothing to read that would say why.
 */
final class DuplicateProvidedIdCheckTest extends TestCase
{
    public function test_a_provider_declaring_each_id_once_passes(): void
    {
        $result = (new DuplicateProvidedIdCheck([$this->module(UniqueIdProvider::class)]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['2 declared id(s), none repeated'], $result->details);
    }

    public function test_a_repeated_id_is_reported_with_both_methods(): void
    {
        $result = (new DuplicateProvidedIdCheck([$this->module(RepeatedIdProvider::class)]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString("'THE_THING' is declared 2 times", $result->details[0]);
        self::assertStringContainsString('early(), late()', $result->details[0]);
    }

    /**
     * The method names are the finding. Told only the id, you still have to
     * find which two of the Provider's methods claim it.
     */
    public function test_the_finding_names_the_provider(): void
    {
        $result = (new DuplicateProvidedIdCheck([$this->module(RepeatedIdProvider::class)]))->run();

        self::assertStringContainsString(RepeatedIdProvider::class, $result->details[0]);
    }

    public function test_the_id_declared_once_beside_it_is_not_reported(): void
    {
        $result = (new DuplicateProvidedIdCheck([$this->module(RepeatedIdProvider::class)]))->run();

        self::assertCount(1, $result->details);
        self::assertStringNotContainsString('THE_OTHER', $result->details[0]);
    }

    /**
     * Each module resolves through its own container, so the same id in two
     * modules is two modules answering for themselves -- not a collision, and
     * reporting it would make the check fire on correct code.
     */
    public function test_the_same_id_in_two_modules_is_not_a_collision(): void
    {
        $result = (new DuplicateProvidedIdCheck([
            $this->module(UniqueIdProvider::class),
            $this->module(UniqueIdProvider::class),
        ]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['4 declared id(s), none repeated'], $result->details);
    }

    public function test_a_module_without_a_provider_is_skipped(): void
    {
        $result = (new DuplicateProvidedIdCheck([$this->module(null)]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['0 declared id(s), none repeated'], $result->details);
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
