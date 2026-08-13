<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Psalm\ServiceMapMissingRules;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

/**
 * The opt-in handler, driven directly.
 *
 * Reporting stays out of reach here -- it goes through Psalm's `IssueBuffer`,
 * which wants a live `ProjectAnalyzer` -- so what is proven is the part that
 * decides: whether the rule runs at all, and what it finds when it does.
 */
final class ServiceMapMissingRulesTest extends TestCase
{
    private const SOURCE = <<<'PHP'
        <?php
        namespace App\Wallet;
        use Gacela\Framework\ServiceResolverAwareTrait;
        /** @method WalletFacade getFacade() */
        final class WalletCommand { use ServiceResolverAwareTrait; }
        PHP;

    protected function tearDown(): void
    {
        // The handler holds its state in a static, because Psalm registers
        // handlers by class-string and calls them statically. Left on, it would
        // decide the result of whichever test ran next.
        ServiceMapMissingRules::configure(false);
    }

    public function test_it_finds_nothing_until_it_is_turned_on(): void
    {
        ServiceMapMissingRules::configure(false);

        self::assertSame([], $this->violations());
    }

    public function test_it_reports_the_accessor_once_turned_on(): void
    {
        ServiceMapMissingRules::configure(true);

        $violations = $this->violations();

        self::assertCount(1, $violations);
        self::assertSame('gacela.serviceMapMissing', $violations[0]->identifier);
    }

    /**
     * Psalm has no separate channel for a tip, so the attribute to paste has to
     * ride along in the message -- both hosts should tell you the same thing.
     */
    public function test_the_correction_survives_into_the_message(): void
    {
        ServiceMapMissingRules::configure(true);

        self::assertStringContainsString(
            "#[ServiceMap(method: 'getFacade', className: WalletFacade::class)]",
            $this->violations()[0]->messageWithTip(),
        );
    }

    /**
     * @return list<Violation>
     */
    private function violations(): array
    {
        return ServiceMapMissingRules::violationsIn(
            ParseSource::classInWithNameAttributes(self::SOURCE),
            new FakeAnalysedClass('App\Wallet\WalletCommand'),
        );
    }
}
