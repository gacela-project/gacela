<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;

use function class_exists;
use function count;
use function interface_exists;
use function sprintf;

/**
 * Every `registerSpecificListener()` target, checked against something an event
 * can actually be.
 *
 * The dispatcher matches by inheritance, so a parent class or an interface is a
 * legitimate target: it covers the whole family below it. What is left is the
 * name that is not a type at all -- a typo, or a class that moved. It is
 * accepted at bootstrap and simply never runs.
 *
 * Only names that can never equal, extend or implement a dispatched event are
 * reported. A type that exists is left alone whether or not it is ever
 * dispatched: a listener for an event this deployment happens not to raise is
 * waiting, not broken.
 */
final class EventListenerTargetCheck implements HealthCheck
{
    /**
     * @param list<string> $listenerTargets      the event names passed to registerSpecificListener()
     * @param int          $genericListenerCount how many registerGenericListener() callables there are;
     *                                           they carry no target, so they are invisible to the checks below
     * @param bool         $dispatcherEnabled    false when `disableEventListeners()` was called, which
     *                                           builds no dispatcher at all. Defaults to Gacela's own default
     */
    public function __construct(
        private readonly array $listenerTargets,
        private readonly int $genericListenerCount = 0,
        private readonly bool $dispatcherEnabled = true,
    ) {
    }

    public function name(): string
    {
        return 'event listeners';
    }

    public function run(): CheckResult
    {
        $registered = count($this->listenerTargets) + $this->genericListenerCount;

        if ($registered === 0) {
            // Disabling with nothing registered is an ordinary production
            // setting rather than a fault: no declaration is going unheard.
            return CheckResult::ok($this->name(), 'no specific listeners registered');
        }

        $warnings = [];

        // The kill switch builds no dispatcher, so everything registered
        // silently does not run -- which `docs/events.md` calls the first thing
        // to check when a listener appears dead. Said before the target
        // problems below, because it applies to all of them at once.
        if (!$this->dispatcherEnabled) {
            $warnings[] = sprintf(
                '%d listener(s) registered, but event listeners are disabled -- none of them runs',
                $registered,
            );
        }

        foreach ($this->listenerTargets as $target) {
            $reason = $this->whyItCanNeverFire($target);

            if ($reason !== null) {
                $warnings[] = sprintf('%s %s', $target, $reason);
            }
        }

        if ($warnings !== []) {
            return CheckResult::warn($this->name(), $warnings, $this->hintFor());
        }

        return CheckResult::ok(
            $this->name(),
            sprintf('%d listener target(s) name a known event type', count($this->listenerTargets)),
        );
    }

    /**
     * The switch first: with the dispatcher off, correcting a target changes
     * nothing until it is back on.
     */
    private function hintFor(): string
    {
        if (!$this->dispatcherEnabled) {
            return 'remove disableEventListeners() to let them run, or drop the registrations -- nothing here can tell a deliberate kill switch from a forgotten one';
        }

        return 'a specific listener runs for the event class named and everything below it -- pass `EventClass::class` rather than a hand-written string, so a rename cannot leave it behind';
    }

    private function whyItCanNeverFire(string $target): ?string
    {
        if (!class_exists($target) && !interface_exists($target)) {
            return 'names no class or interface';
        }

        return null;
    }
}
