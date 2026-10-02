<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\PluginMember;
use Gacela\Framework\Plugins\Membership\TagMember;
use Throwable;

use function array_key_exists;
use function array_map;
use function class_exists;
use function count;
use function in_array;
use function is_a;
use function sprintf;

/**
 * The `#[Plugin]` classes: each must join a stack `gacela.php` declares, and
 * implement its contract. Outside development, scanning for them and for the
 * `#[Tag]` classes on first use is a cost `cache:warm --attributes` removes.
 */
final class PluginMembershipCheck implements HealthCheck
{
    private const DEVELOPMENT_ENVIRONMENTS = ['dev', 'local', 'test', 'testing'];

    /**
     * @param array<string, list<string>> $pluginStacks
     * @param Closure(): Members $scan run by the check, so a class that cannot be read fails it, not the whole `doctor`
     * @param Members|null $cached what the application reads instead of scanning; null when it scans
     */
    public function __construct(
        private readonly array $pluginStacks,
        private readonly Closure $scan,
        private readonly ?Members $cached,
        private readonly ?string $appEnv,
    ) {
    }

    public function name(): string
    {
        return 'plugin and tag attributes';
    }

    public function run(): CheckResult
    {
        try {
            $members = ($this->scan)();
        } catch (Throwable $throwable) {
            return CheckResult::error(
                $this->name(),
                [sprintf('the #[Plugin] and #[Tag] scan failed: %s', $throwable->getMessage())],
                'a #[Plugin] class must load and declare its contract: `#[Plugin(Contract::class)]`',
            );
        }

        $problems = [];
        foreach ($members->plugins as $member) {
            $problem = $this->problemWith($member);
            if ($problem !== null) {
                $problems[] = $problem;
            }
        }

        // What the application really reads, when it reads the cache: a class
        // listed there and gone since fails the stack on its first use.
        foreach ($this->cachedClasses() as $class) {
            if (!class_exists($class)) {
                $problems[] = sprintf('%s — listed in the #[Plugin] and #[Tag] cache, and no such class exists', $class);
            }
        }

        if ($problems !== []) {
            return CheckResult::error(
                $this->name(),
                $problems,
                'declare the stack in gacela.php, empty if the attributes fill it: `$config->addPluginStack(Contract::class, [])`; for a stale cache, run `bin/gacela cache:warm --attributes` or `cache:clear`',
            );
        }

        if ($this->cached instanceof \Gacela\Framework\Plugins\Membership\Members && $this->cached->toRows() !== $members->toRows()) {
            return CheckResult::warn(
                $this->name(),
                ['the #[Plugin] and #[Tag] cache no longer matches the code, so a stack or a tag is missing a member or has one it should not'],
                'run `bin/gacela cache:warm --attributes`, or `cache:clear` to scan again',
            );
        }

        if ($members->count() === 0) {
            return CheckResult::ok($this->name(), 'no #[Plugin] or #[Tag] classes');
        }

        if (!$this->cached instanceof \Gacela\Framework\Plugins\Membership\Members && $this->isProduction()) {
            return CheckResult::warn(
                $this->name(),
                [sprintf('%d #[Plugin] or #[Tag] declaration(s) are found by scanning the module paths on the first use of a stack or tag', $members->count())],
                'run `bin/gacela cache:warm --attributes` when deploying',
            );
        }

        return CheckResult::ok($this->name(), sprintf(
            '%d #[Plugin] and %d #[Tag] declaration(s), %s',
            count($members->plugins),
            count($members->tags),
            $this->cached instanceof \Gacela\Framework\Plugins\Membership\Members ? 'read from the warmed cache' : 'found by scanning on first use',
        ));
    }

    /**
     * @return list<class-string>
     */
    private function cachedClasses(): array
    {
        if (!$this->cached instanceof \Gacela\Framework\Plugins\Membership\Members) {
            return [];
        }

        return [
            ...array_map(static fn (PluginMember $member): string => $member->plugin, $this->cached->plugins),
            ...array_map(static fn (TagMember $member): string => $member->class, $this->cached->tags),
        ];
    }

    private function problemWith(PluginMember $member): ?string
    {
        if (!array_key_exists($member->contract, $this->pluginStacks)) {
            return sprintf('%s — #[Plugin] joins the "%s" stack, which gacela.php does not declare', $member->plugin, $member->contract);
        }

        if (!is_a($member->plugin, $member->contract, true)) {
            return sprintf('%s — #[Plugin] joins the "%s" stack and does not implement it', $member->plugin, $member->contract);
        }

        return null;
    }

    private function isProduction(): bool
    {
        return $this->appEnv !== null
            && $this->appEnv !== ''
            && !in_array($this->appEnv, self::DEVELOPMENT_ENVIRONMENTS, true);
    }
}
