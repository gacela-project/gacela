<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use ReflectionClass;

use function class_exists;
use function count;
use function interface_exists;
use function sprintf;

/**
 * Every `registerSpecificListener()` target, checked against something an event
 * can actually be.
 *
 * The dispatcher matches on `$event::class` -- the exact, concrete class. So a
 * listener registered for an interface never fires, even for events that
 * implement it, and neither does one registered for an abstract class or a name
 * with a typo in it. All three are accepted at bootstrap and simply never run.
 *
 * The interface case is the one worth catching. `Container::afterResolving()`
 * in this same framework matches by `instanceof`, so registering against a
 * contract is a reasonable thing to expect to work, and nothing says otherwise
 * until an event that should have been handled quietly is not.
 *
 * Only names that can never equal `$event::class` are reported. A concrete
 * class that exists is left alone whether or not it is ever dispatched: a
 * listener for an event this deployment happens not to raise is waiting, not
 * broken.
 */
final class EventListenerTargetCheck implements HealthCheck
{
    /**
     * @param list<string> $listenerTargets the event names passed to registerSpecificListener()
     */
    public function __construct(
        private readonly array $listenerTargets,
    ) {
    }

    public function name(): string
    {
        return 'event listeners';
    }

    public function run(): CheckResult
    {
        if ($this->listenerTargets === []) {
            return CheckResult::ok($this->name(), 'no specific listeners registered');
        }

        $warnings = [];

        foreach ($this->listenerTargets as $target) {
            $reason = $this->whyItCanNeverFire($target);

            if ($reason !== null) {
                $warnings[] = sprintf('%s %s', $target, $reason);
            }
        }

        if ($warnings !== []) {
            return CheckResult::warn(
                $this->name(),
                $warnings,
                'a specific listener runs only when the dispatched event is exactly that class -- register the concrete event, or use registerGenericListener() and filter inside it',
            );
        }

        return CheckResult::ok(
            $this->name(),
            sprintf('%d listener target(s) name a concrete event', count($this->listenerTargets)),
        );
    }

    private function whyItCanNeverFire(string $target): ?string
    {
        if (interface_exists($target)) {
            return 'is an interface, and events are matched by exact class';
        }

        if (!class_exists($target)) {
            return 'names no class';
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($target);

        if ($reflection->isAbstract()) {
            return 'is abstract, so no dispatched event can be exactly it';
        }

        return null;
    }
}
