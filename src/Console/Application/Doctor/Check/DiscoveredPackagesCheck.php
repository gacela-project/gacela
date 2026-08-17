<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Debug\PackageDiscoveryReport;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use Gacela\Framework\Bootstrap\Package\PackageDiscovery;
use Gacela\Framework\Bootstrap\Package\PackageRefusal;
use Gacela\Framework\Bootstrap\Package\RefusedPackage;

use function array_fill_keys;
use function count;
use function in_array;
use function is_file;
use function sprintf;

/**
 * Reports a package whose declared Gacela config cannot do what it says.
 *
 * Discovery is deliberately forgiving -- a broken declaration is skipped rather
 * than fatal, because `composer require` must never be able to stop an
 * application from booting. That forgiveness is only safe if something says so
 * out loud afterwards, and this is that something: without it a package author
 * ships a typo and every consumer sees a package that silently does nothing.
 *
 * It also reports the two ways an opt-out can be wrong, which are both invisible
 * otherwise: one written where it arrives too late to have refused anything, and
 * one naming a package that is not installed.
 */
final class DiscoveredPackagesCheck implements HealthCheck
{
    private const string REMEDIATION = 'fix the `extra.gacela.config` path in the package, or drop the key; '
        . 'move an opt-out into `gacela.php` or the bootstrap closure, where it is read before any package runs';

    public function __construct(
        private readonly PackageDiscoveryReport $report,
    ) {
    }

    public function name(): string
    {
        return 'discovered packages';
    }

    public function run(): CheckResult
    {
        if (!$this->report->hasInstalledJson) {
            return CheckResult::ok($this->name(), 'no vendor/composer/installed.json — nothing to discover');
        }

        if ($this->report->declarations === []) {
            return CheckResult::ok($this->name(), 'no installed package declares `extra.gacela.config`');
        }

        $findings = [...$this->brokenDeclarations(), ...$this->uselessOptOuts()];

        if ($findings !== []) {
            return CheckResult::warn($this->name(), $findings, self::REMEDIATION);
        }

        if ($this->report->discoveryDisabled) {
            return CheckResult::ok($this->name(), sprintf(
                '%d package(s) declare a config and none was read — `dontDiscover([\'*\'])`',
                count($this->report->declarations),
            ));
        }

        return CheckResult::ok($this->name(), sprintf(
            '%d of %d declared package config(s) merged',
            count($this->report->discovered),
            count($this->report->declarations),
        ));
    }

    /**
     * @return list<string>
     */
    private function brokenDeclarations(): array
    {
        $findings = [];

        foreach ($this->report->refused as $refused) {
            if ($refused->reason->isBroken()) {
                $findings[] = sprintf(
                    '%s declares %s, which %s',
                    $refused->name,
                    $refused->configFile,
                    $refused->reason->value,
                );
            }
        }

        // Discovery deliberately never opened these, so nothing recorded a
        // verdict on them. Whether the file is *there* is still answerable
        // without running it, and it is the mistake worth catching: a package
        // refused today was installed for its config, and a missing one would
        // otherwise wait to surprise whoever removes the opt-out.
        foreach ($this->unopenedDeclarations() as $declaration) {
            if (!is_file($declaration->configFile)) {
                $findings[] = sprintf(
                    '%s declares %s, which %s (not read: this project opted out of it)',
                    $declaration->name,
                    $declaration->configFile,
                    PackageRefusal::MissingFile->value,
                );
            }
        }

        return $findings;
    }

    /**
     * Refused by name, or never looked at because `dontDiscover(['*'])`
     * stopped the manifest being read at all.
     *
     * @return list<PackageConfigDeclaration>
     */
    private function unopenedDeclarations(): array
    {
        $optedOutByName = [];

        foreach ($this->report->refused as $refused) {
            if (!$refused->reason->isBroken()) {
                $optedOutByName[] = $refused->name;
            }
        }

        $judged = [...$this->report->discoveredNames(), ...array_map(
            static fn (RefusedPackage $refused): string => $refused->name,
            $this->report->refused,
        )];

        $unopened = [];

        foreach ($this->report->declarations as $declaration) {
            if (in_array($declaration->name, $optedOutByName, true) || !in_array($declaration->name, $judged, true)) {
                $unopened[] = $declaration;
            }
        }

        return $unopened;
    }

    /**
     * @return list<string>
     */
    private function uselessOptOuts(): array
    {
        $findings = [];
        // Keyed rather than searched: two `in_array()` negations on one name in
        // a row read to Psalm as the same question asked twice.
        $declared = array_fill_keys($this->report->declaredNames(), true);
        $discovered = array_fill_keys($this->report->discoveredNames(), true);

        foreach ($this->report->optedOut as $name) {
            if ($name === PackageDiscovery::EVERYTHING) {
                continue;
            }

            if (isset($discovered[$name])) {
                // The only way this happens: the opt-out is in
                // `gacela-{APP_ENV}.php`, which is merged after the packages.
                $findings[] = sprintf(
                    'dontDiscover() names %s and its config was merged anyway — an environment file is read after the packages, so the opt-out arrived too late',
                    $name,
                );
                continue;
            }

            if (!isset($declared[$name])) {
                $findings[] = sprintf(
                    'dontDiscover() names %s, which declares no `extra.gacela.config` — the entry refuses nothing',
                    $name,
                );
            }
        }

        return $findings;
    }
}
