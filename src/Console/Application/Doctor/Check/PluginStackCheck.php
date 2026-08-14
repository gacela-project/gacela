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
 * Reports a plugin registered in a stack that cannot satisfy it.
 *
 * `LazyPluginStack` already refuses both shapes -- a class that does not
 * exist, and one that does not implement the contract -- but on *first
 * resolve*, because a class name in `gacela.php` is a string until something
 * loads it. A stack nothing has iterated yet is a registration nobody has
 * checked, so a typo in a rarely-taken path waits until production to be read
 * out.
 *
 * Asking here costs nothing: the answer is the same two questions, put at
 * deploy time instead of at first use.
 */
final class PluginStackCheck implements HealthCheck
{
    /**
     * @param array<class-string, list<class-string>> $pluginStacks contract => the plugins registered under it
     */
    public function __construct(
        private readonly array $pluginStacks,
    ) {
    }

    public function name(): string
    {
        return 'plugin stacks';
    }

    public function run(): CheckResult
    {
        $problems = [];
        $checked = 0;

        foreach ($this->pluginStacks as $contract => $plugins) {
            foreach ($plugins as $plugin) {
                ++$checked;

                $problem = $this->problemWith($plugin, $contract);
                if ($problem !== null) {
                    $problems[] = $problem;
                }
            }
        }

        if ($problems !== []) {
            return CheckResult::error(
                $this->name(),
                $problems,
                'a plugin stack resolves on first use, so this would otherwise surface '
                . 'wherever the stack is first iterated rather than where it was registered',
            );
        }

        if ($checked === 0) {
            return CheckResult::ok($this->name(), 'no plugin stacks registered');
        }

        return CheckResult::ok($this->name(), sprintf('%d plugin(s) satisfy their stack', $checked));
    }

    /**
     * The same two questions `LazyPluginStack` asks, in the same order: a
     * missing class is reported as missing rather than as one that "does not
     * implement" the contract, which is what the container's `null` would turn
     * it into.
     *
     * @param class-string $plugin
     * @param class-string $contract
     */
    private function problemWith(string $plugin, string $contract): ?string
    {
        if (!class_exists($plugin)) {
            return sprintf('%s — registered in the "%s" stack, and no such class exists', $plugin, $contract);
        }

        if (!interface_exists($contract) && !class_exists($contract)) {
            return sprintf('%s — the "%s" stack names a contract that does not exist', $plugin, $contract);
        }

        if (!is_a($plugin, $contract, true)) {
            return sprintf('%s — registered in the "%s" stack and does not implement it', $plugin, $contract);
        }

        return null;
    }
}
