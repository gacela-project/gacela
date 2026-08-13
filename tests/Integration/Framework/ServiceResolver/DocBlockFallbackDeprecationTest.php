<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeAttributeCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeFqcnDocBlockCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeRepeatedCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeSuggestionCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeUseStatementCommand;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * Resolving a pillar from a `@method` docblock, or by scanning the caller's
 * `use` statements, is deprecated in 2.0 and removed in 3.0. `#[ServiceMap]`
 * is the replacement.
 *
 * Without this test the deprecation is invisible: it fires on a **cold resolve
 * only**, because the answer is memoized per caller-and-method. A warm cache
 * stays silent, which is right for production and useless as a guard — so the
 * cache is reset here deliberately.
 */
final class DocBlockFallbackDeprecationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootstrapFresh();
    }

    protected function tearDown(): void
    {
        $this->resetGacela();
    }

    /**
     * Each test below uses its **own** fixture class. The notice is emitted once
     * per caller-and-method for the life of the process, so two tests sharing a
     * fixture would make the second one depend on running first -- and this
     * suite runs in random order.
     */
    public function test_the_use_statement_scan_reports_its_own_strategy(): void
    {
        $notices = $this->capturingDeprecations(static function (): void {
            (new FakeUseStatementCommand())->getFacade();
        });

        self::assertCount(1, $notices);
        self::assertStringContainsString('FakeUseStatementCommand::getFacade()', $notices[0]);
        self::assertStringContainsString("the file's use statements", $notices[0]);
        self::assertStringContainsString('#[ServiceMap(', $notices[0]);
    }

    /**
     * The two fallbacks are separate call sites reporting different strategies.
     * `FakeCommand` names its pillar unqualified, so it resolves through the
     * use-statement scan; this one names it fully-qualified and stops at the
     * docblock. Without both, one call site is never executed.
     */
    public function test_resolving_from_a_fully_qualified_docblock_names_the_docblock_strategy(): void
    {
        $notices = $this->capturingDeprecations(static function (): void {
            (new FakeFqcnDocBlockCommand())->getFacade();
        });

        self::assertCount(1, $notices);
        self::assertStringContainsString('@method docblock', $notices[0]);

        // Asserted on this branch too: it reaches the suggestion with a name
        // the other one does not produce -- written `\`-prefixed in the
        // docblock -- so exactly one branch would otherwise prove the spelling.
        self::assertStringContainsString(
            'className: \\' . FakeFacade::class . '::class)',
            $notices[0],
        );
    }

    /**
     * The resolved class is memoized, so a cache reset would otherwise re-emit
     * an identical message and re-charge sprintf() to cold resolution --
     * measured at +28.59% on `FileCacheBench::bench_without_cache` before this
     * was deduped.
     */
    public function test_the_notice_is_emitted_once_per_caller_and_method(): void
    {
        $notices = $this->capturingDeprecations(function (): void {
            $command = new FakeRepeatedCommand();
            $command->getFacade();

            $this->bootstrapFresh();
            (new FakeRepeatedCommand())->getFacade();
        });

        self::assertCount(1, $notices, 'the same caller and method must report once per process');
    }

    /**
     * The suggestion is only actionable if it names the class. The resolver has
     * just worked the class out -- that is what the notice is reporting -- so
     * printing a literal `className: ...` handed the reader back the one
     * question the fallback had already answered. A pillar named unqualified
     * resolves against the file's imports *and* its namespace, so re-deriving
     * it by hand is not always a matter of reading the `use` block.
     *
     * Leading `\` so the line pastes into any namespace unchanged.
     */
    public function test_the_notice_names_the_class_it_resolved_so_the_suggestion_pastes(): void
    {
        $notices = $this->capturingDeprecations(static function (): void {
            (new FakeSuggestionCommand())->getFacade();
        });

        self::assertCount(1, $notices);
        self::assertStringContainsString(
            "#[ServiceMap(method: 'getFacade', className: \\" . FakeFacade::class . '::class)]',
            $notices[0],
        );
    }

    public function test_declaring_the_pillar_with_the_attribute_raises_nothing(): void
    {
        $notices = $this->capturingDeprecations(static function (): void {
            (new FakeAttributeCommand())->getFacade();
        });

        self::assertSame([], $notices, 'the attribute is the primary path and must stay silent');
    }

    private function bootstrapFresh(): void
    {
        $this->resetGacela();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    /**
     * @param callable():void $body
     *
     * @return list<string>
     */
    private function capturingDeprecations(callable $body): array
    {
        $notices = [];

        set_error_handler(
            static function (int $severity, string $message) use (&$notices): bool {
                $notices[] = $message;

                return true;
            },
            E_USER_DEPRECATED,
        );

        try {
            $body();
        } finally {
            restore_error_handler();
        }

        return $notices;
    }

    private function resetGacela(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        InMemoryCache::resetCache();
    }
}
