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
