<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Framework\AbstractFacade;
use Gacela\Psalm\StorageAnalysedClass;
use PHPUnit\Framework\TestCase;
use Psalm\Codebase;
use Psalm\Storage\ClassLikeStorage;
use ReflectionClass;

/**
 * The seam between the shared rules and Psalm's own view of a class.
 *
 * `ArchitectureRulesTest` exercises it against a real analysis, but that is a
 * subprocess and invisible to coverage; these drive it directly.
 */
final class StorageAnalysedClassTest extends TestCase
{
    private const FACADE = 'App\Checkout\CheckoutFacade';

    public function test_it_reports_the_stored_name(): void
    {
        self::assertSame(self::FACADE, $this->analysedClass()->name());
    }

    /**
     * Psalm keys the ancestry by lowercase name, so the lookup has to lower the
     * name it is given -- `AbstractFacade` would otherwise never match.
     */
    public function test_it_finds_a_parent_class(): void
    {
        $class = $this->analysedClass(parents: [AbstractFacade::class]);

        self::assertTrue($class->extendsClass(AbstractFacade::class));
    }

    public function test_it_reports_an_absent_parent(): void
    {
        self::assertFalse($this->analysedClass()->extendsClass(AbstractFacade::class));
    }

    /**
     * `parent_classes` holds the whole ancestry, not the immediate parent, which
     * is what lets the rules see a facade that extends an intermediate class.
     */
    public function test_it_finds_an_indirect_parent(): void
    {
        $class = $this->analysedClass(parents: ['App\Checkout\BaseFacade', AbstractFacade::class]);

        self::assertTrue($class->extendsClass(AbstractFacade::class));
    }

    /**
     * The values carry the original casing; the keys do not, and reporting a
     * lowercased interface name would name a class that does not exist.
     */
    public function test_it_reports_interfaces_with_their_original_casing(): void
    {
        $class = $this->analysedClass(interfaces: ['App\Checkout\CheckoutFacadeInterface']);

        self::assertSame(['App\Checkout\CheckoutFacadeInterface'], $class->interfaceNames());
    }

    public function test_a_class_implementing_nothing_has_no_interfaces(): void
    {
        self::assertSame([], $this->analysedClass()->interfaceNames());
    }

    /**
     * @param list<string> $parents
     * @param list<string> $interfaces
     */
    private function analysedClass(array $parents = [], array $interfaces = []): StorageAnalysedClass
    {
        $storage = new ClassLikeStorage(self::FACADE);

        foreach ($parents as $parent) {
            $storage->parent_classes[strtolower($parent)] = $parent;
        }

        foreach ($interfaces as $interface) {
            $storage->class_implements[strtolower($interface)] = $interface;
        }

        // Codebase is final and wants a whole analysis context to build; only
        // interfaceHasMethod() reaches for it, and that is covered end-to-end by
        // ArchitectureRulesTest against a real run.
        $codebase = (new ReflectionClass(Codebase::class))->newInstanceWithoutConstructor();

        return new StorageAnalysedClass($storage, $codebase);
    }
}
