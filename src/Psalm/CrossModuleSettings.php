<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Gacela\StaticAnalysis\PublicApiSurface;
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
 *         <ignoreReceiver>App\Modules\Shop\GlobalEnvironmentInterface</ignoreReceiver>
 *         <publicApiSegment>Shared</publicApiSegment>
 *         <publicApiSegment>Transfer</publicApiSegment>
 *     </crossModule>
 * </pluginClass>
 * ```
 *
 * `<publicApiSegment>` names a sub-namespace *under a module* that the module
 * publishes, so `App\Modules\Shop\Transfer\Order` may be reached from anywhere
 * without going through `ShopFacade`. Written nowhere, the default is
 * {@see PublicApiSurface::DEFAULT_SEGMENTS}; a single empty `<publicApiSegment/>`
 * turns the convention off and leaves `#[PublicApi]` as the only way to export.
 *
 * Not to be confused with `<sharedNamespace>`, which is a fully-qualified prefix
 * belonging to no module at all.
 */
final class CrossModuleSettings
{
    /**
     * @param list<string> $sharedNamespaces
     * @param list<string> $ignoreReceivers   read by the method-call half only: it is
     *                                        about what a call may land on, and the other
     *                                        half matches written names rather than receivers
     * @param list<string> $publicApiSegments read by both halves: what a module
     *                                        exports is the same question whether
     *                                        its name is written or its type resolved
     */
    private function __construct(
        public readonly string $rootNamespace,
        public readonly int $modulePathSegments,
        public readonly array $sharedNamespaces,
        public readonly array $ignoreReceivers = [],
        public readonly array $publicApiSegments = PublicApiSurface::DEFAULT_SEGMENTS,
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
            PluginXml::ignoreReceivers($crossModule),
            PluginXml::publicApiSegments($crossModule),
        );
    }
}
