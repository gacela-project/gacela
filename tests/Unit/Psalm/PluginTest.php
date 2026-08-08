<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Framework\AbstractFactory;
use Gacela\Psalm\ClassRules;
use Gacela\Psalm\Plugin;
use PHPUnit\Framework\TestCase;
use Psalm\Codebase;
use Psalm\Config;
use Psalm\Internal\Codebase\Methods;
use Psalm\Internal\EventDispatcher;
use Psalm\Internal\Provider\MethodReturnTypeProvider;
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
    private ?MethodReturnTypeProvider $returnTypes = null;

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

    public function test_it_registers_the_provided_dependency_return_type_handler(): void
    {
        $returnTypes = $this->returnTypeProvider();

        self::assertFalse(
            $returnTypes->has(AbstractFactory::class),
            'precondition: nothing is registered for the factory before the plugin runs',
        );

        (new Plugin())($this->socket(new EventDispatcher()));

        self::assertTrue($returnTypes->has(AbstractFactory::class));
    }

    public function test_it_registers_the_class_level_architecture_rules(): void
    {
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher));

        self::assertContains(ClassRules::class, $dispatcher->after_classlike_checks);
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
        $socketReflection->getProperty('codebase')->setValue($socket, $this->codebase());

        return $socket;
    }

    /**
     * A return-type provider registers against the codebase rather than the
     * event dispatcher, so the socket needs one -- but only the two fields the
     * registration walks through.
     */
    private function codebase(): Codebase
    {
        $methods = (new ReflectionClass(Methods::class))->newInstanceWithoutConstructor();
        $methods->return_type_provider = $this->returnTypeProvider();

        $codebase = (new ReflectionClass(Codebase::class))->newInstanceWithoutConstructor();
        $codebase->methods = $methods;

        return $codebase;
    }

    /**
     * Its handlers live in a static, so constructing one is also how the
     * registry is emptied between tests.
     */
    private function returnTypeProvider(): MethodReturnTypeProvider
    {
        return $this->returnTypes ??= new MethodReturnTypeProvider();
    }
}
