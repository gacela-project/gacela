<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Psalm\Exception\ConfigException;
use SimpleXMLElement;

/**
 * The declared-module-rules check's configuration, read off the
 * `<pluginClass>` element.
 *
 * ```xml
 * <pluginClass class="Gacela\Psalm\Plugin">
 *     <moduleRules rootNamespace="App\Modules" file="module-rules.json" modulePathSegments="1">
 *         <sharedNamespace>App\Modules\Shared</sharedNamespace>
 *     </moduleRules>
 * </pluginClass>
 * ```
 *
 * `file` is the same JSON `debug:graph --check --rules` reads, resolved from
 * Psalm's working directory. One file, two readers: a boundary that held in CI
 * and not in the editor would be a boundary nobody trusts.
 */
final class ModuleRulesSettings
{
    /**
     * @param list<string> $sharedNamespaces
     */
    private function __construct(
        public readonly string $rootNamespace,
        public readonly string $file,
        public readonly int $modulePathSegments,
        public readonly array $sharedNamespaces,
    ) {
    }

    /**
     * Null when no `<moduleRules>` is present, which is the check staying off.
     * A `<moduleRules>` missing either of its two required attributes throws
     * instead: a rule that quietly does nothing reads as a green check.
     *
     * @throws ConfigException
     */
    public static function fromPluginConfig(?SimpleXMLElement $config): ?self
    {
        $moduleRules = PluginXml::element($config?->moduleRules);
        if (!$moduleRules instanceof SimpleXMLElement) {
            return null;
        }

        return new self(
            PluginXml::requiredAttribute(
                $moduleRules,
                'rootNamespace',
                'without it there is no way to tell where a module begins.',
            ),
            PluginXml::requiredAttribute(
                $moduleRules,
                'file',
                'the rules to check are the ones written in that file.',
            ),
            PluginXml::modulePathSegments($moduleRules),
            PluginXml::sharedNamespaces($moduleRules),
        );
    }
}
