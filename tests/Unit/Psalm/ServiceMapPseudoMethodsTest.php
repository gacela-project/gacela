<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Psalm\ServiceMapPseudoMethods;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PHPUnit\Framework\TestCase;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeVisitEvent;
use Psalm\Storage\ClassLikeStorage;
use ReflectionClass;

/**
 * Drives the Psalm hook directly, in-process.
 *
 * `ServiceMapPluginTest` already runs the plugin end-to-end through a real
 * `vendor/bin/psalm`, which is the stronger proof that it works — but that runs
 * in a subprocess, so coverage cannot see it and every mutant in this namespace
 * counted as untested. These tests exist to put the logic back under the
 * mutation gate; the subprocess test stays as the integration check.
 */
final class ServiceMapPseudoMethodsTest extends TestCase
{
    private const FACADE = 'App\Billing\BillingFacade';

    public function test_it_registers_a_pseudo_method_from_the_attribute(): void
    {
        $storage = $this->visit($this->serviceMapAttribute('getFacade', self::FACADE));

        // Psalm looks pseudo-methods up by lowercase name, but reports the cased
        // one, so both halves matter.
        self::assertArrayHasKey('getfacade', $storage->pseudo_methods);
        self::assertSame('getFacade', $storage->pseudo_methods['getfacade']->cased_name);
        self::assertSame(self::FACADE, (string)$storage->pseudo_methods['getfacade']->return_type);
    }

    public function test_the_pseudo_method_is_not_static(): void
    {
        $storage = $this->visit($this->serviceMapAttribute('getFacade', self::FACADE));

        self::assertFalse($storage->pseudo_methods['getfacade']->is_static);
    }

    public function test_it_reads_positional_arguments(): void
    {
        // #[ServiceMap('getFactory', SomeFactory::class)] — no argument names.
        $storage = $this->visit($this->serviceMapAttribute('getFactory', self::FACADE, named: false));

        self::assertSame('getFactory', $storage->pseudo_methods['getfactory']->cased_name);
    }

    public function test_it_registers_every_repeated_attribute(): void
    {
        $storage = $this->visit(
            $this->serviceMapAttribute('getFacade', self::FACADE),
            $this->serviceMapAttribute('getConfig', 'App\Billing\BillingConfig'),
        );

        self::assertSame(['getfacade', 'getconfig'], array_keys($storage->pseudo_methods));
    }

    public function test_it_ignores_an_unrelated_attribute(): void
    {
        $other = new Name('SomethingElse');
        $other->setAttribute('resolvedName', 'App\SomethingElse');

        $storage = $this->visit(new Attribute($other, [
            new Arg(new String_('getFacade'), name: new Identifier('method')),
        ]));

        self::assertSame([], $storage->pseudo_methods);
    }

    public function test_it_matches_an_unresolved_fully_qualified_name(): void
    {
        // Written out in full in the source, so the resolver left no
        // `resolvedName` and toString() is the only name available.
        $storage = $this->visit($this->serviceMapAttribute(
            'getFacade',
            self::FACADE,
            attributeName: new Name(ServiceMap::class),
        ));

        self::assertArrayHasKey('getfacade', $storage->pseudo_methods);
    }

    public function test_it_matches_a_leading_backslash(): void
    {
        $storage = $this->visit($this->serviceMapAttribute(
            'getFacade',
            self::FACADE,
            attributeName: new Name('\\' . ServiceMap::class),
        ));

        self::assertArrayHasKey('getfacade', $storage->pseudo_methods);
    }

    public function test_it_ignores_a_method_argument_that_is_not_a_literal_string(): void
    {
        $storage = $this->visit($this->attributeWithArgs([
            new Arg(new Variable('method'), name: new Identifier('method')),
            new Arg($this->classConstFetch(self::FACADE), name: new Identifier('className')),
        ]));

        self::assertSame([], $storage->pseudo_methods);
    }

    public function test_it_ignores_a_class_name_argument_that_is_not_a_class_constant(): void
    {
        $storage = $this->visit($this->attributeWithArgs([
            new Arg(new String_('getFacade'), name: new Identifier('method')),
            new Arg(new String_(self::FACADE), name: new Identifier('className')),
        ]));

        self::assertSame([], $storage->pseudo_methods);
    }

    public function test_it_ignores_a_class_constant_whose_class_is_an_expression(): void
    {
        // `$var::class` — the left side is not a Name, so no class name resolves.
        $storage = $this->visit($this->attributeWithArgs([
            new Arg(new String_('getFacade'), name: new Identifier('method')),
            new Arg(
                new ClassConstFetch(new Variable('var'), new Identifier('class')),
                name: new Identifier('className'),
            ),
        ]));

        self::assertSame([], $storage->pseudo_methods);
    }

    public function test_it_ignores_an_attribute_missing_the_method_argument(): void
    {
        $storage = $this->visit($this->attributeWithArgs([
            new Arg($this->classConstFetch(self::FACADE), name: new Identifier('className')),
        ]));

        self::assertSame([], $storage->pseudo_methods);
    }

    public function test_it_ignores_an_attribute_missing_the_class_name_argument(): void
    {
        $storage = $this->visit($this->attributeWithArgs([
            new Arg(new String_('getFacade'), name: new Identifier('method')),
        ]));

        self::assertSame([], $storage->pseudo_methods);
    }

    public function test_it_ignores_an_attribute_with_no_arguments(): void
    {
        $storage = $this->visit($this->attributeWithArgs([]));

        self::assertSame([], $storage->pseudo_methods);
    }

    public function test_it_registers_nothing_for_a_class_without_attributes(): void
    {
        $storage = $this->visit();

        self::assertSame([], $storage->pseudo_methods);
    }

    private function visit(Attribute ...$attributes): ClassLikeStorage
    {
        $groups = [];
        foreach ($attributes as $attribute) {
            // One group per attribute: repeated attributes may be written either
            // stacked or comma-separated, and both reach here as groups.
            $groups[] = new AttributeGroup([$attribute]);
        }

        $storage = new ClassLikeStorage('App\Billing\Consumer');
        ServiceMapPseudoMethods::afterClassLikeVisit(
            $this->event(new Class_('Consumer', ['attrGroups' => $groups]), $storage),
        );

        return $storage;
    }

    /**
     * The event is a value object and the hook reads only two of its five fields,
     * but its constructor also demands a `Codebase`, which is `final` and so
     * neither mockable nor cheap to build. Bypassing the constructor keeps the
     * test about the hook.
     */
    private function event(ClassLike $stmt, ClassLikeStorage $storage): AfterClassLikeVisitEvent
    {
        $reflection = new ReflectionClass(AfterClassLikeVisitEvent::class);
        $event = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('stmt')->setValue($event, $stmt);
        $reflection->getProperty('storage')->setValue($event, $storage);

        return $event;
    }

    private function serviceMapAttribute(
        string $method,
        string $className,
        bool $named = true,
        ?Name $attributeName = null,
    ): Attribute {
        $args = $named
            ? [
                new Arg(new String_($method), name: new Identifier('method')),
                new Arg($this->classConstFetch($className), name: new Identifier('className')),
            ]
            : [
                new Arg(new String_($method)),
                new Arg($this->classConstFetch($className)),
            ];

        return $this->attributeWithArgs($args, $attributeName);
    }

    /**
     * @param list<Arg> $args
     */
    private function attributeWithArgs(array $args, ?Name $attributeName = null): Attribute
    {
        if ($attributeName === null) {
            // How an imported `#[ServiceMap]` reaches the hook: the short name in
            // the source, the resolved one left on the node by the resolver.
            $attributeName = new Name('ServiceMap');
            $attributeName->setAttribute('resolvedName', ServiceMap::class);
        }

        return new Attribute($attributeName, $args);
    }

    private function classConstFetch(string $className): Expr
    {
        $fetch = new ClassConstFetch(new Name('BillingFacade'), new Identifier('class'));
        $fetch->class->setAttribute('resolvedName', $className);

        return $fetch;
    }
}
