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
 */
final class Plugin implements PluginEntryPointInterface
{
    public function __invoke(PluginRegistrationSocket $registration, ?SimpleXMLElement $config = null): void
    {
        // Psalm checks class_exists($handler, false) -- autoloading disabled --
        // so the handler has to be loaded before it can be registered.
        require_once __DIR__ . '/ServiceMapPseudoMethods.php';

        $registration->registerHooksFromClass(ServiceMapPseudoMethods::class);
    }
}
