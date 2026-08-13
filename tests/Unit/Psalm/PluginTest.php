<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Framework\AbstractFactory;
use Gacela\Psalm\ClassRules;
use Gacela\Psalm\CrossModuleCallRules;
use Gacela\Psalm\CrossModuleRules;
use Gacela\Psalm\DeclaredModuleDependencyRules;
use Gacela\Psalm\Plugin;
use Gacela\Psalm\ServiceMapMissingRules;
use PHPUnit\Framework\TestCase;
use Psalm\Codebase;
use Psalm\Config;
use Psalm\Exception\ConfigException;
use Psalm\Internal\Codebase\Methods;
use Psalm\Internal\EventDispatcher;
use Psalm\Internal\Provider\MethodReturnTypeProvider;
use Psalm\PluginRegistrationSocket;
use ReflectionClass;
use SimpleXMLElement;

use function sprintf;

/**
 * `ServiceMapPluginTest` proves the plugin works by running a real
 * `vendor/bin/psalm`, but that is a subprocess and invisible to coverage. These
 * tests drive the entry point in-process so its behaviour is under the mutation
 * gate too.
 */
final class PluginTest extends TestCase
{
    private ?MethodReturnTypeProvider $returnTypes = null;

    /** @var list<string> */
    private array $files = [];

    /**
     * Registering the plugin with a <crossModule> element sets a static on each
     * handler. Left set, it decides the outcome of any later test that asks
     * whether the check is on.
     */
    protected function tearDown(): void
    {
        CrossModuleRules::configure(null);
        CrossModuleCallRules::configure(null);
        DeclaredModuleDependencyRules::configure(null);

        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->files = [];
    }

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

    /**
     * Nothing in a class name says where a module boundary falls, so this one
     * cannot be on by default -- and staying off has to be the behaviour, not an
     * accident of nobody calling it.
     */
    public function test_it_leaves_the_cross_module_check_off_without_config(): void
    {
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher));

        self::assertNotContains(CrossModuleRules::class, $dispatcher->after_classlike_checks);
        self::assertNotContains(CrossModuleCallRules::class, $dispatcher->after_expression_checks);
        self::assertFalse(CrossModuleRules::isConfigured());
        self::assertFalse(CrossModuleCallRules::isConfigured());
    }

    /**
     * Both halves go on together: one matches the module names a source writes,
     * the other resolves the receivers it does not.
     */
    public function test_a_cross_module_element_turns_both_halves_on(): void
    {
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher), new SimpleXMLElement(
            '<pluginClass><crossModule rootNamespace="App\Modules"/></pluginClass>',
        ));

        self::assertContains(CrossModuleRules::class, $dispatcher->after_classlike_checks);
        self::assertContains(CrossModuleCallRules::class, $dispatcher->after_expression_checks);
        self::assertTrue(CrossModuleRules::isConfigured());
        self::assertTrue(CrossModuleCallRules::isConfigured());
    }

    /**
     * The rules live in a file the consumer writes, so with no file named there
     * is nothing to check -- and staying off has to be the behaviour, not an
     * accident of nobody calling it.
     */
    public function test_it_leaves_the_module_rules_check_off_without_config(): void
    {
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher));

        self::assertNotContains(DeclaredModuleDependencyRules::class, $dispatcher->after_classlike_checks);
        self::assertFalse(DeclaredModuleDependencyRules::isConfigured());
    }

    public function test_a_module_rules_element_turns_the_check_on(): void
    {
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher), new SimpleXMLElement(sprintf(
            '<pluginClass><moduleRules rootNamespace="App\Modules" file="%s"/></pluginClass>',
            $this->writeRulesFile(),
        )));

        self::assertContains(DeclaredModuleDependencyRules::class, $dispatcher->after_classlike_checks);
        self::assertTrue(DeclaredModuleDependencyRules::isConfigured());
    }

    /**
     * What this one reports is a deprecation rather than a mistake, so off is
     * the behaviour a project that has not asked for it must get -- an upgrade
     * that turned it on would fail a build over code that works.
     */
    public function test_it_leaves_the_service_map_check_off_without_config(): void
    {
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher));

        self::assertNotContains(ServiceMapMissingRules::class, $dispatcher->after_classlike_checks);
        self::assertFalse(ServiceMapMissingRules::isConfigured());
    }

    /**
     * The element carries nothing: there is nothing to configure, only the
     * decision to start the 3.0 migration.
     */
    public function test_a_service_map_missing_element_turns_the_check_on(): void
    {
        $dispatcher = new EventDispatcher();

        (new Plugin())(
            $this->socket($dispatcher),
            new SimpleXMLElement('<pluginClass><serviceMapMissing/></pluginClass>'),
        );

        self::assertContains(ServiceMapMissingRules::class, $dispatcher->after_classlike_checks);
        self::assertTrue(ServiceMapMissingRules::isConfigured());
    }

    /**
     * Configured on every invocation, so a second run with the element gone
     * turns it back off rather than leaving what the first one set -- the
     * handler holds its state in a static, which outlives one invocation.
     */
    public function test_a_later_config_without_the_element_turns_it_back_off(): void
    {
        (new Plugin())(
            $this->socket(new EventDispatcher()),
            new SimpleXMLElement('<pluginClass><serviceMapMissing/></pluginClass>'),
        );

        (new Plugin())($this->socket(new EventDispatcher()));

        self::assertFalse(ServiceMapMissingRules::isConfigured());
    }

    public function test_a_module_rules_element_without_a_file_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);

        (new Plugin())(
            $this->socket(new EventDispatcher()),
            new SimpleXMLElement('<pluginClass><moduleRules rootNamespace="App\Modules"/></pluginClass>'),
        );
    }

    public function test_a_module_rules_element_without_a_root_namespace_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);

        (new Plugin())(
            $this->socket(new EventDispatcher()),
            new SimpleXMLElement('<pluginClass><moduleRules file="module-rules.json"/></pluginClass>'),
        );
    }

    public function test_a_cross_module_element_without_a_root_namespace_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);

        (new Plugin())(
            $this->socket(new EventDispatcher()),
            new SimpleXMLElement('<pluginClass><crossModule/></pluginClass>'),
        );
    }

    public function test_it_ignores_the_optional_plugin_config(): void
    {
        // Psalm passes the <pluginClass> element when one carries child config.
        $dispatcher = new EventDispatcher();

        (new Plugin())($this->socket($dispatcher), new SimpleXMLElement('<pluginClass/>'));

        self::assertTrue($dispatcher->hasAfterClassLikeVisitHandlers());
    }

    /**
     * A real file, because the plugin reads the rules while registering: an
     * unreadable one is a setup error and has to be one here too.
     */
    private function writeRulesFile(): string
    {
        $path = sys_get_temp_dir() . '/gacela-plugin-rules-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode([
            'rules' => [['from' => 'App\Modules\Payment', 'deny' => ['App\Modules\Admin'], 'reason' => 'reviewed']],
        ], JSON_THROW_ON_ERROR));
        $this->files[] = $path;

        return $path;
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
