<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\Config\EnvironmentLayer;

use function count;
use function explode;
use function implode;
use function sprintf;

/**
 * Which files a base config path matched and the base layer does not read.
 *
 * `addAppConfig('config/*.php')` globs literally, so it also matches the
 * environment files the framework names itself -- `app-prod.php`,
 * `app-prod-eu.php` -- and used to read every one of them into the base layer,
 * on every run, before the chain that selects one was applied. The loader now
 * excludes them by name; see {@see EnvironmentLayer} for the rule.
 *
 * The rule reads filenames, and a filename does not carry intent. A project with
 * `config/app.php` and a genuinely unrelated `config/app-extra.php` has the
 * second one excluded too, and read only where the chain resolves to `extra`.
 * That is what this check is for: an exclusion nobody is told about would trade
 * one silent wrong value for a silent missing one, which is no better. Every
 * excluded file is named here, with the base file it is taken to refine and the
 * values that put it in play, so a project whose file is not an environment
 * layer can see it and rename it.
 *
 * Reported as a pass, not a warning: the arrangement is the correct one for
 * every project that uses `APP_ENV` or a dimension at all, and warning on it
 * would fail `doctor --strict` on all of them.
 */
final class ConfigEnvironmentLayerCheck implements HealthCheck
{
    /**
     * @param list<EnvironmentLayer> $excludedLayers
     * @param list<string> $configDimensions declared with `addConfigDimension()`, in chain order
     */
    public function __construct(
        private readonly array $excludedLayers,
        private readonly array $configDimensions,
    ) {
    }

    public function name(): string
    {
        return 'config environment layers';
    }

    public function run(): CheckResult
    {
        if ($this->excludedLayers === []) {
            return CheckResult::ok(
                $this->name(),
                'no config file is treated as an environment layer of another',
            );
        }

        return CheckResult::ok(
            $this->name(),
            array_map(
                fn (EnvironmentLayer $layer): string => sprintf(
                    '%s matches a base config path but is excluded from it: an environment layer of %s, read only when %s',
                    $layer->path,
                    $layer->basePath,
                    $this->whenLoaded($layer->suffix),
                ),
                $this->excludedLayers,
            ),
            'A file named after another one plus -<suffix> is read only in that environment. If one of these is not an environment layer, rename it or give it its own addAppConfig() path.',
        );
    }

    /**
     * The values that put one layer in play.
     *
     * The suffix is the whole chain, and the chain is `APP_ENV` followed by each
     * declared dimension in declaration order, so naming the variables is a
     * matter of pairing them off. More segments than the chain has links means
     * the split cannot be named: with no dimension declared, `app-prod-eu.php`
     * is `APP_ENV=prod-eu` -- a hyphen is legal in an environment name -- and
     * printing a variable this project never declared would be worse than
     * printing the suffix as it stands.
     */
    private function whenLoaded(string $suffix): string
    {
        $variables = ['APP_ENV', ...$this->configDimensions];
        $segments = explode('-', $suffix);

        if (count($segments) > count($variables)) {
            return sprintf('the environment chain resolves to "%s"', $suffix);
        }

        $pairs = [];
        foreach ($segments as $index => $segment) {
            $pairs[] = $variables[$index] . '=' . $segment;
        }

        return implode(' and ', $pairs);
    }
}
