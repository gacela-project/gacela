<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Psalm\Exception\ConfigException;
use SimpleXMLElement;

use function count;
use function trim;

/**
 * The cross-module check's configuration, read off the `<pluginClass>` element.
 *
 * Nothing in a class name says where a module boundary falls, so unlike the
 * pillar rules this one cannot be on by default -- it needs the consumer's root
 * namespace:
 *
 * ```xml
 * <pluginClass class="Gacela\Psalm\Plugin">
 *     <crossModule rootNamespace="App\Modules" modulePathSegments="1">
 *         <sharedNamespace>App\Modules\Shared</sharedNamespace>
 *     </crossModule>
 * </pluginClass>
 * ```
 */
final class CrossModuleSettings
{
    /**
     * @param list<string> $sharedNamespaces
     */
    private function __construct(
        public readonly string $rootNamespace,
        public readonly int $modulePathSegments,
        public readonly array $sharedNamespaces,
    ) {
    }

    /**
     * Null when no `<crossModule>` is present, which is the rule staying off --
     * the same default as the commented-out block in `phpstan-gacela.neon`.
     *
     * A `<crossModule>` without a `rootNamespace` throws instead. A rule that
     * quietly does nothing is worse than no rule: it reads as a green check, and
     * nothing would ever tell you the boundary was never being checked.
     *
     * @throws ConfigException
     */
    public static function fromPluginConfig(?SimpleXMLElement $config): ?self
    {
        if (!$config instanceof SimpleXMLElement) {
            return null;
        }

        // Reading an absent child yields an *empty* element rather than null,
        // so the count is what says whether one was written. `instanceof` alone
        // is true either way, and would turn "not configured" into "configured
        // with nothing", which throws.
        $crossModule = $config->crossModule;
        if (count($crossModule) === 0) {
            return null;
        }

        $rootNamespace = trim((string)($crossModule['rootNamespace'] ?? ''));

        if ($rootNamespace === '') {
            throw new ConfigException(
                '<crossModule> needs a rootNamespace: without it there is no way to tell where a module begins.',
            );
        }

        return new self(
            $rootNamespace,
            self::modulePathSegments($crossModule),
            self::sharedNamespaces($crossModule),
        );
    }

    private static function modulePathSegments(SimpleXMLElement $crossModule): int
    {
        $segments = trim((string)($crossModule['modulePathSegments'] ?? ''));

        if ($segments === '') {
            return 1;
        }

        if ((int)$segments < 1) {
            throw new ConfigException(
                'modulePathSegments must be a positive number of namespace segments, got: ' . $segments,
            );
        }

        return (int)$segments;
    }

    /**
     * @return list<string>
     */
    private static function sharedNamespaces(SimpleXMLElement $crossModule): array
    {
        $namespaces = [];

        foreach ($crossModule->sharedNamespace as $sharedNamespace) {
            $name = trim((string)$sharedNamespace);
            if ($name !== '') {
                $namespaces[] = $name;
            }
        }

        return $namespaces;
    }
}
