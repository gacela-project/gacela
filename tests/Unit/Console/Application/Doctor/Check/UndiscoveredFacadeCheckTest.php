<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\UndiscoveredFacadeCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeFile;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeProblem;
use PHPUnit\Framework\TestCase;

final class UndiscoveredFacadeCheckTest extends TestCase
{
    public function test_a_project_where_every_facade_resolves_passes(): void
    {
        $result = (new UndiscoveredFacadeCheck([]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['every Facade-named file resolves to a module'], $result->details);
    }

    /**
     * A warning rather than an error, because `Facade` is an ordinary word and a
     * project beside a framework with its own facades would otherwise have its
     * build failed by a naming coincidence. `--strict` is how a project opts in.
     */
    public function test_a_finding_warns_rather_than_fails(): void
    {
        $result = (new UndiscoveredFacadeCheck([$this->notLoadable()]))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
    }

    public function test_it_names_the_class_the_reason_and_the_file(): void
    {
        $result = (new UndiscoveredFacadeCheck([$this->notLoadable()]))->run();

        self::assertSame(
            ['App\\Blog\\BlogFacade — php cannot load this class (/app/src/Blog/BlogFacade.php)'],
            $result->details,
        );
    }

    public function test_it_distinguishes_a_class_that_loads_but_is_not_a_facade(): void
    {
        $result = (new UndiscoveredFacadeCheck([$this->notAFacade()]))->run();

        self::assertStringContainsString('does not extend AbstractFacade', $result->details[0]);
    }

    /**
     * The two faults have different fixes, which is the whole reason to tell
     * them apart rather than report "not found".
     */
    /**
     * Whole sentences, not fragments of them. A remediation is the one line a
     * reader acts on, and `assertStringContainsString('psr-4', ...)` passes just
     * as happily for half a sentence in the wrong order.
     */
    public function test_the_remediation_matches_the_kind_of_fault(): void
    {
        $loadable = (new UndiscoveredFacadeCheck([$this->notLoadable()]))->run();
        $notFacade = (new UndiscoveredFacadeCheck([$this->notAFacade()]))->run();

        self::assertSame(
            'check the psr-4 prefix covers the directory and the namespace declaration '
            . 'matches the path, then `composer dump-autoload`',
            $loadable->remediation,
        );

        self::assertSame(
            'extend AbstractFacade, or rename the class if it was never meant to be a module',
            $notFacade->remediation,
        );
    }

    /**
     * Both at once is the run where a single-fault remediation would send the
     * reader to fix the wrong half.
     */
    public function test_a_mixed_run_names_both_fixes(): void
    {
        $result = (new UndiscoveredFacadeCheck([$this->notLoadable(), $this->notAFacade()]))->run();

        self::assertSame(
            'for the unloadable ones check the psr-4 prefix and `composer dump-autoload`; '
            . 'for the rest extend AbstractFacade, or rename the class if it was never meant to be a module',
            $result->remediation,
        );
        self::assertCount(2, $result->details);
    }

    private function notLoadable(): UndiscoveredFacadeFile
    {
        return new UndiscoveredFacadeFile(
            '/app/src/Blog/BlogFacade.php',
            'App\\Blog\\BlogFacade',
            UndiscoveredFacadeProblem::NotLoadable,
        );
    }

    private function notAFacade(): UndiscoveredFacadeFile
    {
        return new UndiscoveredFacadeFile(
            '/app/src/Shop/ShopFacade.php',
            'App\\Shop\\ShopFacade',
            UndiscoveredFacadeProblem::NotAFacade,
        );
    }
}
