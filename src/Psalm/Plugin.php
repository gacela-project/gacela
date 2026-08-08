<?php

declare(strict_types=1);

namespace Gacela\Psalm;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\PluginRegistrationSocket;
use SimpleXMLElement;

/**
 * Register in `psalm.xml`:
 *
 * ```xml
 * <plugins>
 *     <pluginClass class="Gacela\Psalm\Plugin"/>
 * </plugins>
 * ```
 *
 * The cross-module check is opt-in, because nothing in a class name says where a
 * module boundary falls -- add a `<crossModule>` child to turn it on. See
 * {@see CrossModuleSettings}.
 */
final class Plugin implements PluginEntryPointInterface
{
    public function __invoke(PluginRegistrationSocket $registration, ?SimpleXMLElement $config = null): void
    {
        // Psalm checks class_exists($handler, false) -- autoloading disabled --
        // so the handler has to be loaded before it can be registered.
        require_once __DIR__ . '/ServiceMapPseudoMethods.php';
        require_once __DIR__ . '/ProvidedDependencyReturnType.php';
        require_once __DIR__ . '/ClassRules.php';
        require_once __DIR__ . '/CrossModuleRules.php';
        require_once __DIR__ . '/CrossModuleCallRules.php';

        $registration->registerHooksFromClass(ServiceMapPseudoMethods::class);
        $registration->registerHooksFromClass(ProvidedDependencyReturnType::class);
        $registration->registerHooksFromClass(ClassRules::class);

        $this->registerCrossModule($registration, CrossModuleSettings::fromPluginConfig($config));
    }

    private function registerCrossModule(PluginRegistrationSocket $registration, ?CrossModuleSettings $settings): void
    {
        // Two halves of one check, so they are turned on together: one matches
        // the names a source writes, the other resolves the receivers it does
        // not. Configured even when there is nothing to configure, so the state
        // is what the current config says rather than what an earlier one left.
        CrossModuleRules::configure($settings);
        CrossModuleCallRules::configure($settings);

        if (!$settings instanceof CrossModuleSettings) {
            return;
        }

        $registration->registerHooksFromClass(CrossModuleRules::class);
        $registration->registerHooksFromClass(CrossModuleCallRules::class);
    }
}
