<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Debug\PackageDiscoveryReport;
use Gacela\Console\Application\Doctor\Check\DiscoveredPackagesCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\Bootstrap\Package\DiscoveredPackage;
use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use Gacela\Framework\Bootstrap\Package\PackageContribution;
use Gacela\Framework\Bootstrap\Package\RefusedPackage;
use PHPUnit\Framework\TestCase;

use function implode;

final class DiscoveredPackagesCheckTest extends TestCase
{
    private const string MISSING = '/no/such/place/config/gacela.php';

    private const string ALSO_MISSING = '/no/such/other/place/config/gacela.php';

    public function test_it_is_named_after_what_it_reports_on(): void
    {
        self::assertSame('discovered packages', $this->check($this->report())->name());
    }

    public function test_without_installed_json_there_is_nothing_to_judge(): void
    {
        $result = $this->check($this->report(hasInstalledJson: false))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no vendor/composer/installed.json — nothing to discover'], $result->details);
    }

    public function test_an_installation_where_no_package_declares_a_config(): void
    {
        $result = $this->check($this->report())->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no installed package declares `extra.gacela.config`'], $result->details);
    }

    public function test_it_counts_what_was_merged_against_what_was_declared(): void
    {
        $declaration = $this->declaration('acme/audit');

        $result = $this->check($this->report(
            declarations: [$declaration, $this->declaration('acme/other')],
            discovered: [$this->discovered($declaration)],
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 of 2 declared package config(s) merged'], $result->details);
    }

    public function test_a_declaration_pointing_at_a_file_that_is_not_there(): void
    {
        $declaration = $this->declaration('acme/audit', self::MISSING);

        $result = $this->check($this->report(
            declarations: [$declaration],
            refused: [RefusedPackage::missingFile($declaration)],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['acme/audit declares ' . self::MISSING . ', which file not found'], $result->details);
        self::assertStringContainsString('fix the `extra.gacela.config` path', $result->remediation);
    }

    public function test_a_config_that_does_not_return_a_callable(): void
    {
        $declaration = $this->declaration('acme/audit');

        $result = $this->check($this->report(
            declarations: [$declaration],
            refused: [RefusedPackage::notCallable($declaration)],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['acme/audit declares ' . __FILE__ . ', which does not return a callable'], $result->details);
    }

    /**
     * Every broken declaration, not the first one: a package author who
     * mistyped one path has usually mistyped the other, and a check that
     * reported one of two would send them round the loop twice.
     */
    public function test_every_broken_declaration_is_reported_and_not_just_the_first(): void
    {
        $missing = $this->declaration('acme/audit', self::MISSING);
        $notCallable = $this->declaration('acme/legacy');

        $result = $this->check($this->report(
            declarations: [$missing, $notCallable],
            refused: [RefusedPackage::missingFile($missing), RefusedPackage::notCallable($notCallable)],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([
            'acme/audit declares ' . self::MISSING . ', which file not found',
            'acme/legacy declares ' . __FILE__ . ', which does not return a callable',
        ], $result->details);
    }

    /**
     * A package whose config was merged is described as merged, whatever the
     * disk says by the time this runs. The `is_file()` question below is only
     * asked about declarations discovery never opened -- asking it about one it
     * did would report a package that ran as a package this project refused,
     * which is the opposite of what happened.
     */
    public function test_a_merged_package_is_never_reported_as_one_the_project_refused(): void
    {
        $declaration = $this->declaration('acme/audit', self::MISSING);

        $result = $this->check($this->report(
            declarations: [$declaration],
            discovered: [$this->discovered($declaration)],
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 of 1 declared package config(s) merged'], $result->details);
    }

    /**
     * The refusal is the project getting what it asked for. Reporting it would
     * teach people to ignore this check.
     */
    public function test_a_working_opt_out_is_not_a_finding(): void
    {
        $declaration = $this->declaration('acme/legacy');

        $result = $this->check($this->report(
            declarations: [$declaration],
            refused: [RefusedPackage::optedOut($declaration)],
            optedOut: ['acme/legacy'],
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['0 of 1 declared package config(s) merged'], $result->details);
    }

    /**
     * Still worth naming: an opt-out is a package installed for its config, and
     * whoever removes the entry will meet the broken declaration then.
     */
    public function test_an_opted_out_package_whose_config_is_missing_is_still_named(): void
    {
        $declaration = $this->declaration('acme/legacy', self::MISSING);

        $result = $this->check($this->report(
            declarations: [$declaration],
            refused: [RefusedPackage::optedOut($declaration)],
            optedOut: ['acme/legacy'],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('not read: this project opted out of it', implode("\n", $result->details));
    }

    /**
     * Two opt-outs are two answers, and the second one is not the first one's
     * business: whoever removes either entry meets that package's broken
     * declaration then.
     */
    public function test_every_opted_out_package_with_a_missing_config_is_named(): void
    {
        $first = $this->declaration('acme/legacy', self::MISSING);
        $second = $this->declaration('acme/older', self::ALSO_MISSING);

        $result = $this->check($this->report(
            declarations: [$first, $second],
            refused: [RefusedPackage::optedOut($first), RefusedPackage::optedOut($second)],
            optedOut: ['acme/legacy', 'acme/older'],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([
            'acme/legacy declares ' . self::MISSING . ', which file not found (not read: this project opted out of it)',
            'acme/older declares ' . self::ALSO_MISSING . ', which file not found (not read: this project opted out of it)',
        ], $result->details);
    }

    /**
     * `'*'` is the one entry that names no package, so it is passed over --
     * passed over, not treated as the end of the list. An application that
     * refuses everything and still carries a stale name has both, and the stale
     * name is the one worth telling it about.
     */
    public function test_the_wildcard_does_not_stop_the_entries_after_it_being_judged(): void
    {
        $result = $this->check($this->report(
            discoveryDisabled: true,
            declarations: [$this->declaration('acme/audit')],
            optedOut: ['*', 'acme/never-installed'],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['dontDiscover() names acme/never-installed, which declares no `extra.gacela.config` — the entry refuses nothing'],
            $result->details,
        );
    }

    public function test_an_opt_out_that_arrived_too_late_does_not_hide_the_next_one(): void
    {
        $declaration = $this->declaration('acme/audit');

        $result = $this->check($this->report(
            declarations: [$declaration],
            discovered: [$this->discovered($declaration)],
            optedOut: ['acme/audit', 'acme/never-installed'],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame([
            'dontDiscover() names acme/audit and its config was merged anyway — an environment file is read after the packages, so the opt-out arrived too late',
            'dontDiscover() names acme/never-installed, which declares no `extra.gacela.config` — the entry refuses nothing',
        ], $result->details);
    }

    public function test_dont_discover_everything_reads_nothing_and_says_so(): void
    {
        $result = $this->check($this->report(
            discoveryDisabled: true,
            declarations: [$this->declaration('acme/audit')],
            optedOut: ['*'],
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(["1 package(s) declare a config and none was read — `dontDiscover(['*'])`"], $result->details);
    }

    /**
     * `'*'` names no package, so the "refuses nothing" rule below must not fire
     * on it.
     */
    public function test_the_wildcard_is_not_reported_as_an_entry_that_refuses_nothing(): void
    {
        $result = $this->check($this->report(
            discoveryDisabled: true,
            declarations: [$this->declaration('acme/audit', self::MISSING)],
            optedOut: ['*'],
        ))->run();

        self::assertSame(
            ['acme/audit declares ' . self::MISSING . ', which file not found (not read: this project opted out of it)'],
            $result->details,
        );
    }

    /**
     * The only way an opt-out can be in the merged setup and the package be
     * merged anyway: it was written in `gacela-{APP_ENV}.php`, which is read
     * after the packages.
     */
    public function test_an_opt_out_that_arrived_after_the_package_had_already_run(): void
    {
        $declaration = $this->declaration('acme/audit');

        $result = $this->check($this->report(
            declarations: [$declaration],
            discovered: [$this->discovered($declaration)],
            optedOut: ['acme/audit'],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['dontDiscover() names acme/audit and its config was merged anyway — an environment file is read after the packages, so the opt-out arrived too late'],
            $result->details,
        );
    }

    public function test_an_opt_out_naming_a_package_that_declares_nothing(): void
    {
        $declaration = $this->declaration('acme/audit');

        $result = $this->check($this->report(
            declarations: [$declaration],
            discovered: [$this->discovered($declaration)],
            optedOut: ['acme/never-installed'],
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(
            ['dontDiscover() names acme/never-installed, which declares no `extra.gacela.config` — the entry refuses nothing'],
            $result->details,
        );
    }

    private function check(PackageDiscoveryReport $report): DiscoveredPackagesCheck
    {
        return new DiscoveredPackagesCheck($report);
    }

    /**
     * @param list<PackageConfigDeclaration> $declarations
     * @param list<DiscoveredPackage>        $discovered
     * @param list<RefusedPackage>           $refused
     * @param list<string>                   $optedOut
     */
    private function report(
        bool $hasInstalledJson = true,
        bool $discoveryDisabled = false,
        array $declarations = [],
        array $discovered = [],
        array $refused = [],
        array $optedOut = [],
    ): PackageDiscoveryReport {
        return new PackageDiscoveryReport(
            $hasInstalledJson,
            $discoveryDisabled,
            $declarations,
            $discovered,
            $refused,
            $optedOut,
        );
    }

    /**
     * Defaults to this very file, which is the cheapest path that certainly
     * exists — the check only ever asks whether it is there.
     */
    private function declaration(string $name, string $configFile = __FILE__): PackageConfigDeclaration
    {
        return new PackageConfigDeclaration($name, 'config/gacela.php', $configFile);
    }

    private function discovered(PackageConfigDeclaration $declaration): DiscoveredPackage
    {
        return new DiscoveredPackage(
            $declaration->name,
            $declaration->configFile,
            1,
            PackageContribution::fromArray([]),
        );
    }
}
