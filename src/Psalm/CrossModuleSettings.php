<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Psalm\Exception\ConfigException;
use SimpleXMLElement;

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
        $crossModule = PluginXml::element($config?->crossModule);
        if (!$crossModule instanceof SimpleXMLElement) {
            return null;
        }

        return new self(
            PluginXml::requiredAttribute(
                $crossModule,
                'rootNamespace',
                'without it there is no way to tell where a module begins.',
            ),
            PluginXml::modulePathSegments($crossModule),
            PluginXml::sharedNamespaces($crossModule),
        );
    }
}
