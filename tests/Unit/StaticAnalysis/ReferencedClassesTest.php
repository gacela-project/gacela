<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis;

use Gacela\StaticAnalysis\ReferencedClasses;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One case per way a class can name another one.
 *
 * The module graph is built from `use` statements, and every one of these costs
 * the file an import -- so a kind missing here is a dependency the CLI gate sees
 * and the editor does not, which is exactly the disagreement the rules exist to
 * avoid.
 */
final class ReferencedClassesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function referenceProvider(): iterable
    {
        yield 'new' => ['new \App\Other\Thing();', 'App\Other\Thing'];
        yield 'static call' => ['\App\Other\Thing::go();', 'App\Other\Thing'];
        yield 'class constant' => ['$x = \App\Other\Thing::class;', 'App\Other\Thing'];
        yield 'static property' => ['$x = \App\Other\Thing::$instances;', 'App\Other\Thing'];
        yield 'instanceof' => ['$x = $y instanceof \App\Other\Thing;', 'App\Other\Thing'];
        yield 'catch' => ['try { $x = 1; } catch (\App\Other\Thing $e) {}', 'App\Other\Thing'];
    }

    #[DataProvider('referenceProvider')]
    public function test_it_finds_a_reference_written_in_a_method_body(string $body, string $expected): void
    {
        self::assertContains($expected, $this->referencesIn(
            "<?php\nfinal class Subject\n{\n    public function run()\n    {\n" . $body . "\n    }\n}",
        ));
    }

    public function test_it_finds_the_parent_class(): void
    {
        self::assertContains('App\Other\Base', $this->referencesIn(
            '<?php final class Subject extends \App\Other\Base {}',
        ));
    }

    public function test_it_finds_an_implemented_interface(): void
    {
        self::assertContains('App\Other\Contract', $this->referencesIn(
            '<?php final class Subject implements \App\Other\Contract {}',
        ));
    }

    public function test_it_finds_an_interface_a_declared_interface_extends(): void
    {
        self::assertContains('App\Other\Contract', $this->referencesIn(
            '<?php interface Subject extends \App\Other\Contract {}',
        ));
    }

    public function test_it_finds_an_interface_an_enum_implements(): void
    {
        self::assertContains('App\Other\Contract', $this->referencesIn(
            '<?php enum Subject implements \App\Other\Contract { case One; }',
        ));
    }

    public function test_it_finds_a_used_trait(): void
    {
        self::assertContains('App\Other\Helper', $this->referencesIn(
            '<?php final class Subject { use \App\Other\Helper; }',
        ));
    }

    public function test_it_finds_an_attribute(): void
    {
        self::assertContains('App\Other\Marker', $this->referencesIn(
            '<?php #[\App\Other\Marker] final class Subject {}',
        ));
    }

    public function test_it_finds_a_parameter_type(): void
    {
        self::assertContains('App\Other\Thing', $this->referencesIn(
            '<?php final class Subject { public function run(\App\Other\Thing $t): void {} }',
        ));
    }

    public function test_it_finds_a_return_type(): void
    {
        self::assertContains('App\Other\Thing', $this->referencesIn(
            '<?php final class Subject { public function run(): \App\Other\Thing {} }',
        ));
    }

    public function test_it_finds_a_property_type(): void
    {
        self::assertContains('App\Other\Thing', $this->referencesIn(
            '<?php final class Subject { private \App\Other\Thing $thing; }',
        ));
    }

    public function test_it_finds_a_nullable_type(): void
    {
        self::assertContains('App\Other\Thing', $this->referencesIn(
            '<?php final class Subject { public function run(?\App\Other\Thing $t): void {} }',
        ));
    }

    public function test_it_finds_every_arm_of_a_union_type(): void
    {
        $references = $this->referencesIn(
            '<?php final class Subject { public function run(\App\Other\A|\App\Other\B $t): void {} }',
        );

        self::assertContains('App\Other\A', $references);
        self::assertContains('App\Other\B', $references);
    }

    public function test_it_finds_every_arm_of_an_intersection_type(): void
    {
        $references = $this->referencesIn(
            '<?php final class Subject { public function run(\App\Other\A&\App\Other\B $t): void {} }',
        );

        self::assertContains('App\Other\A', $references);
        self::assertContains('App\Other\B', $references);
    }

    public function test_a_builtin_type_names_no_class(): void
    {
        self::assertSame([], $this->referencesIn(
            '<?php final class Subject { public function run(string $s): int {} }',
        ));
    }

    /**
     * `new $class` and `$class::CONST` name nothing a rule could match.
     */
    public function test_a_dynamic_class_name_is_not_a_reference(): void
    {
        self::assertSame([], $this->referencesIn(
            '<?php final class Subject { public function run($class) { new $class(); } }',
        ));
    }

    public function test_one_class_named_many_times_is_listed_once(): void
    {
        self::assertSame(['App\Other\Thing'], $this->referencesIn(
            '<?php final class Subject { public function run() { new \App\Other\Thing(); \App\Other\Thing::go(); } }',
        ));
    }

    /**
     * @return list<string>
     */
    private function referencesIn(string $php): array
    {
        return ReferencedClasses::in(ParseSource::classInAsPhpStanResolves($php));
    }
}
