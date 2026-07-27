<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Psalm\Plugin;
use PHPUnit\Framework\TestCase;
use Psalm\Config;
use Psalm\Internal\EventDispatcher;
use Psalm\PluginRegistrationSocket;
use ReflectionClass;
use SimpleXMLElement;

/**
 * `ServiceMapPluginTest` proves the plugin works by running a real
 * `vendor/bin/psalm`, but that is a subprocess and invisible to coverage. These
 * tests drive the entry point in-process so its behaviour is under the mutation
 * gate too.
 */
final class PluginTest extends TestCase
{
    public function test_it_registers_the_pseudo_method_handler(): void
    {
        $dispatcher = new EventDispatcher();

        self::assertFalse(
            $dispatcher->hasAfterClassLikeVisitHandlers(),
            'precondition: nothing is registered before the plugin runs',
        );

        (new Plugin())($this->socket($dispatcher));

        self::assertTrue($dispatcher->hasAfterClassLikeVisitHandlers());
    }

    public function test_it_ignores_the_optional_plugin_config(): void
    {
        // Psalm passes the <pluginClass> element when one carries child config.
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher), new SimpleXMLElement('<pluginClass/>'));

        self::assertTrue($dispatcher->hasAfterClassLikeVisitHandlers());
    }

    /**
     * Psalm registers handlers by class-string with autoloading disabled, so the
     * plugin has to `require_once` the handler itself. Registration succeeding is
     * what proves that happened — Psalm would reject an unloaded class.
     */
    private function socket(EventDispatcher $dispatcher): PluginRegistrationSocket
    {
        $configReflection = new ReflectionClass(Config::class);
        $config = $configReflection->newInstanceWithoutConstructor();
        $configReflection->getProperty('eventDispatcher')->setValue($config, $dispatcher);

        // Both Config and the socket are final with non-public constructors that
        // want a whole analysis context; the entry point touches neither.
        $socketReflection = new ReflectionClass(PluginRegistrationSocket::class);
        $socket = $socketReflection->newInstanceWithoutConstructor();
        $socketReflection->getProperty('config')->setValue($socket, $config);

        return $socket;
    }
}
