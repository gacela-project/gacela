<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules;

use Composer\Autoload\ClassLoader;
use Gacela\Console\Domain\AllAppModules\FacadeAncestry;
use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Util\DirectoryUtil;
use GacelaTest\Unit\Console\Domain\AllAppModules\Fixtures\NotLoaded\ExtendsNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class FacadeAncestryTest extends TestCase
{
    private string $dir;

    /** @var array<string, string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gacela_facade_ancestry_' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir($this->dir);
    }

    public function test_rules_out_a_class_that_extends_nothing(): void
    {
        $this->declare('Anc\Plain', "namespace Anc;\n\nfinal class Plain\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Plain'));
    }

    public function test_rules_out_a_class_declared_in_another_case(): void
    {
        $this->declare('Anc\Plain', "namespace Anc;\n\nFINAL CLASS plain\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Plain'));
    }

    public function test_rules_out_a_chain_that_ends_without_reaching_abstract_facade(): void
    {
        $this->declare('Anc\Child', "namespace Anc;\n\nfinal class Child extends Base\n{\n}");
        $this->declare('Anc\Base', "namespace Anc;\n\nabstract class Base extends Root implements \\Countable\n{\n}");
        $this->declare('Anc\Root', "namespace Anc;\n\nabstract class Root\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Child'));
    }

    public function test_rules_out_by_reflection_once_the_chain_reaches_a_loaded_class(): void
    {
        $this->declare('Anc\Test', "namespace Anc;\n\nuse PHPUnit\\Framework\\TestCase;\n\nfinal class Test extends TestCase\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Test'));
        self::assertTrue($this->ancestry()->rulesOut(self::class));
    }

    /**
     * Each import shape resolves the parent to `Other\Deep\Root`, which ends
     * the chain, so the class is ruled out only when the name is resolved right.
     */
    #[DataProvider('importProvider')]
    public function test_resolves_the_parent_through_the_imports(string $source): void
    {
        $this->declare('Anc\Real', $source);
        $this->declare('Other\Deep\Root', "namespace Other\\Deep;\n\nabstract class Root\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Real'));
    }

    public static function importProvider(): iterable
    {
        yield 'imported' => ["namespace Anc;\n\nuse Other\\Deep\\Root;\n\nfinal class Real extends Root\n{\n}"];
        yield 'aliased' => ["namespace Anc;\n\nuse Other\\Deep\\Root as Parent_;\n\nfinal class Real extends Parent_\n{\n}"];
        yield 'alias in another case' => ["namespace Anc;\n\nuse Other\\Deep\\Root;\n\nfinal class Real extends ROOT\n{\n}"];
        yield 'one of several in a statement' => ["namespace Anc;\n\nuse Some\\Thing, Other\\Deep\\Root;\n\nfinal class Real extends Root\n{\n}"];
        yield 'qualified through an imported namespace' => ["namespace Anc;\n\nuse Other\\Deep;\n\nfinal class Real extends Deep\\Root\n{\n}"];
        yield 'qualified through a top-level namespace' => ["namespace Anc;\n\nuse Other;\n\nfinal class Real extends Other\\Deep\\Root\n{\n}"];
        yield 'fully qualified' => ["namespace Anc;\n\nfinal class Real extends \\Other\\Deep\\Root\n{\n}"];
        yield 'function and const imports are skipped' => ["namespace Anc;\n\nuse Other\\Deep\\Root;\nuse function Root;\nuse const Root;\n\nfinal class Real extends Root\n{\n}"];
        yield 'prose in a docblock is not an import' => ["namespace Anc;\n\nuse Other\\Deep\\Root;\n\n/**\n * Because Missing as Root;\n */\nfinal class Real extends Root\n{\n}"];
        yield 'wrapped declaration' => ["namespace Anc;\n\nuse Other\\Deep\\Root;\n\nfinal class Real\n    extends Root\n    implements \\Countable\n{\n}"];
    }

    public function test_resolves_an_unimported_parent_in_the_same_namespace(): void
    {
        $this->declare('Anc\Real', "namespace Anc;\n\nfinal class Real extends Root\n{\n}");
        $this->declare('Anc\Root', "namespace Anc;\n\nabstract class Root\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Real'));
    }

    public function test_resolves_a_parent_in_the_global_namespace(): void
    {
        $this->declare('Real', "final class Real extends Root\n{\n}");
        $this->declare('Root', "abstract class Root\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Real'));
    }

    #[DataProvider('facadeParentProvider')]
    public function test_does_not_rule_out_a_chain_that_reaches_abstract_facade(string $source): void
    {
        $this->declare('Anc\Real', $source);
        $this->declare('Anc\BaseFacade', "namespace Anc;\n\nuse Gacela\\Framework\\AbstractFacade;\n\nabstract class BaseFacade extends AbstractFacade\n{\n}");

        self::assertFalse($this->ancestry()->rulesOut('Anc\Real'));
    }

    public static function facadeParentProvider(): iterable
    {
        yield 'directly' => ["namespace Anc;\n\nuse Gacela\\Framework\\AbstractFacade;\n\nfinal class Real extends AbstractFacade\n{\n}"];
        yield 'written in another case' => ["namespace Anc;\n\nfinal class Real extends \\gacela\\framework\\ABSTRACTFACADE\n{\n}"];
        yield 'through a project base facade' => ["namespace Anc;\n\nfinal class Real extends BaseFacade\n{\n}"];
    }

    /**
     * Each of these would be ruled out if the parse trusted it, because the
     * parent it would resolve to (`Anc\Root` or `Other\Root`) ends the chain.
     */
    #[DataProvider('unknownProvider')]
    public function test_does_not_rule_out_what_the_source_cannot_prove(string $source): void
    {
        $this->declare('Anc\Real', $source);
        $this->declare('Anc\Root', "namespace Anc;\n\nabstract class Root\n{\n}");
        $this->declare('Other\Root', "namespace Other;\n\nabstract class Root\n{\n}");

        self::assertFalse($this->ancestry()->rulesOut('Anc\Real'));
    }

    public static function unknownProvider(): iterable
    {
        yield 'parent Composer cannot locate' => ["namespace Anc;\n\nfinal class Real extends Missing\n{\n}"];
        yield 'group use' => ["namespace Anc;\n\nuse Other\\{Root};\n\nfinal class Real extends Root\n{\n}"];
        yield 'body before the declaration' => ["namespace Anc;\n\nfunction helper(): void {}\n\nfinal class Real extends Root\n{\n}"];
        yield 'two namespaces' => ["namespace Other;\n\nnamespace Anc;\n\nfinal class Real extends Root\n{\n}"];
        yield 'a commented-out declaration first' => ["namespace Anc;\n\n// class Real {\nfinal class Real extends \\Gacela\\Framework\\AbstractFacade\n{\n}"];
        yield 'comment between name and extends' => ["namespace Anc;\n\nfinal class Real /* x */ extends Root\n{\n}"];
        yield 'class declared under another name' => ["namespace Anc;\n\nfinal class Other extends Root\n{\n}"];
    }

    public function test_does_not_rule_out_a_parent_cycle(): void
    {
        $this->declare('Anc\A', "namespace Anc;\n\nclass A extends B\n{\n}");
        $this->declare('Anc\B', "namespace Anc;\n\nclass B extends A\n{\n}");

        $ancestry = $this->ancestry();

        self::assertFalse($ancestry->rulesOut('Anc\A'));
        self::assertFalse($ancestry->rulesOut('Anc\B'));
    }

    public function test_does_not_rule_out_a_located_file_that_is_gone(): void
    {
        $this->files['Anc\Gone'] = $this->dir . '/Gone.php';

        self::assertFalse($this->ancestry()->rulesOut('Anc\Gone'));
    }

    public function test_does_not_rule_out_abstract_facade_itself(): void
    {
        self::assertFalse($this->ancestry()->rulesOut(AbstractFacade::class));
    }

    public function test_rules_out_nothing_without_a_class_loader(): void
    {
        $this->declare('Anc\Plain', "namespace Anc;\n\nfinal class Plain\n{\n}");

        self::assertFalse((new FacadeAncestry())->rulesOut('Anc\Plain'));
    }

    public function test_asks_each_class_loader_in_turn(): void
    {
        $this->declare('Anc\Plain', "namespace Anc;\n\nfinal class Plain\n{\n}");

        $ancestry = new FacadeAncestry([new ClassLoader(), $this->classLoader()]);

        self::assertTrue($ancestry->rulesOut('Anc\Plain'));
    }

    public function test_the_registered_autoloaders_locate_a_project_class(): void
    {
        $ancestry = FacadeAncestry::fromRegisteredAutoloaders();

        self::assertTrue($ancestry->rulesOut(ExtendsNothing::class));
        self::assertFalse($ancestry->rulesOut('GacelaTest\Unit\Console\Domain\AllAppModules\Fixtures\NotAutoloadable\Missing'));
    }

    private function declare(string $className, string $body): void
    {
        $path = sprintf('%s/%s.php', $this->dir, str_replace('\\', '_', $className));
        file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\n" . $body . "\n");
        $this->files[$className] = $path;
    }

    private function ancestry(): FacadeAncestry
    {
        return new FacadeAncestry([$this->classLoader()]);
    }

    private function classLoader(): ClassLoader
    {
        $classLoader = new ClassLoader();
        $classLoader->addClassMap($this->files);

        return $classLoader;
    }
}
