<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\Plugins\Membership\PluginMember;

use function array_key_exists;
use function count;
use function in_array;
use function is_a;
use function sprintf;

/**
 * The `#[Plugin]` classes: each must join a stack `gacela.php` declares, and
 * implement its contract. Outside development, scanning for them on first use
 * is a cost `cache:warm --attributes` removes.
 */
final class PluginMembershipCheck implements HealthCheck
{
    private const DEVELOPMENT_ENVIRONMENTS = ['dev', 'local', 'test', 'testing'];

    /**
     * @param array<string, list<string>> $pluginStacks
     * @param list<PluginMember> $members
     */
    public function __construct(
        private readonly array $pluginStacks,
        private readonly array $members,
        private readonly bool $cacheIsWarm,
        private readonly ?string $appEnv,
    ) {
    }

    public function name(): string
    {
        return 'plugin attributes';
    }

    public function run(): CheckResult
    {
        if ($this->members === []) {
            return CheckResult::ok($this->name(), 'no #[Plugin] classes');
        }

        $problems = [];
        foreach ($this->members as $member) {
            $problem = $this->problemWith($member);
            if ($problem !== null) {
                $problems[] = $problem;
            }
        }

        if ($problems !== []) {
            return CheckResult::error(
                $this->name(),
                $problems,
                'declare the stack in gacela.php, empty if the attributes fill it: '
                . '`$config->addPluginStack(Contract::class, [])`',
            );
        }

        if (!$this->cacheIsWarm && $this->isProduction()) {
            return CheckResult::warn(
                $this->name(),
                [sprintf('%d #[Plugin] class(es) are found by scanning the module paths on the first use of a stack', count($this->members))],
                'run `bin/gacela cache:warm --attributes` when deploying',
            );
        }

        return CheckResult::ok($this->name(), sprintf(
            '%d #[Plugin] class(es) join declared stacks, %s',
            count($this->members),
            $this->cacheIsWarm ? 'read from the warmed cache' : 'found by scanning on first use',
        ));
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
