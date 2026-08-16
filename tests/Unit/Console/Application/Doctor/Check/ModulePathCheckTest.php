<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\ModulePathCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;

final class ModulePathCheckTest extends TestCase
{
    public function test_every_configured_path_being_a_directory_is_ok(): void
    {
        $result = (new ModulePathCheck(['src', 'lib'], []))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['2 configured path(s) scanned'], $result->details);
    }

    /**
     * The whole point of the check: every module-scoped check below it works
     * from what discovery returned, so a path that scanned nothing narrows all
     * of them at once and the run still ends in a screen of ticks.
     */
    public function test_a_path_that_is_not_a_directory_is_a_warning_naming_it(): void
    {
        $result = (new ModulePathCheck(['src'], ['not-there']))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['appModulePaths entry "not-there" is not a directory, so nothing under it was scanned'],
            $result->details,
        );
    }

    public function test_every_unscannable_path_is_named_not_just_the_first(): void
    {
        $result = (new ModulePathCheck([], ['not-there', 'also/missing']))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertCount(2, $result->details);
    }

    /**
     * `--strict` is how a project turns this into a build failure, so the
     * remediation has to name the call that fixes it rather than the concept.
     */
    public function test_the_remediation_names_the_method_that_configures_the_paths(): void
    {
        $result = (new ModulePathCheck([], ['not-there']))->run();

        self::assertStringContainsString('GacelaConfig::setAppModulePaths()', $result->remediation);
    }

    /**
     * Configuring nothing is the default and means "scan the project root",
     * which discovery reports as one path. An empty pair means the config was
     * read and answered with nothing, which is not a fault to report either --
     * but it must not read as "2 paths scanned".
     */
    public function test_no_configured_paths_at_all_is_ok_and_says_so(): void
    {
        $result = (new ModulePathCheck([], []))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['0 configured path(s) scanned'], $result->details);
    }
}
