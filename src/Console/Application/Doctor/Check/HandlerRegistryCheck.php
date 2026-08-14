<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;

use function class_exists;
use function interface_exists;
use function is_a;
use function sprintf;

/**
 * Reports a handler registered under a key that cannot satisfy its registry.
 *
 * The sibling of {@see PluginStackCheck}, for the keyed half of the same idea,
 * and the case for it is stronger. `LazyHandlerRegistry` resolves a key through
 * the container on first access, so:
 *
 *  - a class that does not exist raises a `TypeError` wherever that key is
 *    first looked up -- late, and on the consumer rather than on the
 *    registration;
 *  - a class that does not implement the registry's contract is *not* refused
 *    at all. It is handed back, and the failure surfaces as a call to a method
 *    it does not have, somewhere else entirely.
 *
 * A registry is looked up by key, so the keys a run does not take are exactly
 * the registrations nobody has checked -- which is most of them, most of the
 * time.
 */
final class HandlerRegistryCheck implements HealthCheck
{
    /**
     * The contract key is a plain string, not a `class-string`: a project can
     * name one that does not exist, and saying so is one of the findings.
     *
     * @param array<string, array<array-key, class-string>> $handlerRegistries contract => key => handler
     */
    public function __construct(
        private readonly array $handlerRegistries,
    ) {
    }

    public function name(): string
    {
        return 'handler registries';
    }

    public function run(): CheckResult
    {
        $problems = [];
        $checked = 0;

        foreach ($this->handlerRegistries as $contract => $handlers) {
            foreach ($handlers as $key => $handler) {
                ++$checked;

                $problem = $this->problemWith($handler, $contract, $key);
                if ($problem !== null) {
                    $problems[] = $problem;
                }
            }
        }

        if ($problems !== []) {
            return CheckResult::error(
                $this->name(),
                $problems,
                'a registry resolves a key on first lookup, so this would otherwise surface '
                . 'wherever that key is first asked for rather than where it was registered',
            );
        }

        if ($checked === 0) {
            return CheckResult::ok($this->name(), 'no handler registries registered');
        }

        return CheckResult::ok($this->name(), sprintf('%d handler(s) satisfy their registry', $checked));
    }

    /**
     * The key is named in every finding. A registry is a map, so "which
     * handler" is not enough to act on -- the same class can be registered
     * under more than one key, and the key is what a consumer asks for.
     *
     * @param class-string $handler
     */
    private function problemWith(string $handler, string $contract, string|int $key): ?string
    {
        if (!class_exists($handler)) {
            return sprintf('%s — registered as "%s" in the "%s" registry, and no such class exists', $handler, $key, $contract);
        }

        if (!interface_exists($contract) && !class_exists($contract)) {
            return sprintf('%s — the "%s" registry names a contract that does not exist', $handler, $contract);
        }

        if (!is_a($handler, $contract, true)) {
            return sprintf('%s — registered as "%s" in the "%s" registry and does not implement it', $handler, $key, $contract);
        }

        return null;
    }
}
