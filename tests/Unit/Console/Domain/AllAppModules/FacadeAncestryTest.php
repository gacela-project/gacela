<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\AllAppModules;

use Gacela\Console\Domain\AllAppModules\FacadeAncestry;
use GacelaTest\Feature\Util\DirectoryUtil;
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

    public function test_rules_out_a_chain_that_ends_without_reaching_abstract_facade(): void
    {
        $this->declare('Anc\Child', "namespace Anc;\n\nuse Other\\Base as Parent_;\n\nfinal class Child extends Parent_\n{\n}");
        $this->declare('Other\Base', "namespace Other;\n\nabstract class Base extends Root\n{\n}");
        $this->declare('Other\Root', "namespace Other;\n\nabstract class Root implements \\Countable\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Child'));
    }

    public function test_rules_out_by_reflection_once_the_chain_reaches_a_loaded_class(): void
    {
        $this->declare('Anc\Test', "namespace Anc;\n\nuse PHPUnit\\Framework\\TestCase;\n\nfinal class Test extends TestCase\n{\n}");

        self::assertTrue($this->ancestry()->rulesOut('Anc\Test'));
    }

    #[DataProvider('facadeParentProvider')]
    public function test_does_not_rule_out_a_chain_that_reaches_abstract_facade(string $source): void
    {
        $this->declare('Anc\Real', $source);

        self::assertFalse($this->ancestry()->rulesOut('Anc\Real'));
    }

    public static function facadeParentProvider(): iterable
    {
        yield 'imported' => ["namespace Anc;\n\nuse Gacela\\Framework\\AbstractFacade;\n\nfinal class Real extends AbstractFacade\n{\n}"];
        yield 'aliased' => ["namespace Anc;\n\nuse Gacela\\Framework\\AbstractFacade as Base;\n\nfinal class Real extends Base\n{\n}"];
        yield 'qualified through an imported namespace' => ["namespace Anc;\n\nuse Gacela\\Framework;\n\nfinal class Real extends Framework\\AbstractFacade\n{\n}"];
        yield 'fully qualified' => ["namespace Anc;\n\nfinal class Real extends \\Gacela\\Framework\\AbstractFacade\n{\n}"];
        yield 'wrapped' => ["namespace Anc;\n\nuse Gacela\\Framework\\AbstractFacade;\n\nfinal class Real\n    extends AbstractFacade\n    implements \\Countable\n{\n}"];
    }

    public function test_does_not_rule_out_through_a_project_base_facade(): void
    {
        $this->declare('Anc\Real', "namespace Anc;\n\nfinal class Real extends BaseFacade\n{\n}");
        $this->declare('Anc\BaseFacade', "namespace Anc;\n\nuse Gacela\\Framework\\AbstractFacade;\n\nabstract class BaseFacade extends AbstractFacade\n{\n}");

        self::assertFalse($this->ancestry()->rulesOut('Anc\Real'));
    }

    /**
     * Each of these is a shape the parse cannot be sure of, so the answer is
     * "unknown" and the caller loads the class instead.
     */
    #[DataProvider('unknownProvider')]
    public function test_does_not_rule_out_what_the_source_cannot_prove(string $source): void
    {
        $this->declare('Anc\Real', $source);

        self::assertFalse($this->ancestry()->rulesOut('Anc\Real'));
    }

    public static function unknownProvider(): iterable
    {
        yield 'parent Composer cannot locate' => ["namespace Anc;\n\nfinal class Real extends Missing\n{\n}"];
        yield 'group use' => ["namespace Anc;\n\nuse Other\\{Base};\n\nfinal class Real extends Base\n{\n}"];
        yield 'two declarations' => ["namespace Anc;\n\nif (true) {\n    class Real extends A {}\n} else {\n    class Real extends B {}\n}"];
        yield 'comment between name and extends' => ["namespace Anc;\n\nfinal class Real /* x */ extends Base\n{\n}"];
        yield 'mentioned in a comment' => ["namespace Anc;\n\n// class Real is special\nfinal class Real\n{\n}"];
        yield 'body before the declaration' => ["namespace Anc;\n\nfunction helper(): void {}\n\nfinal class Real extends Base\n{\n}"];
        yield 'namespace-relative parent' => ["namespace Anc;\n\nfinal class Real extends namespace\\Base\n{\n}"];
        yield 'class declared under another name' => ["namespace Anc;\n\nfinal class Other\n{\n}"];
    }

    public function test_does_not_rule_out_a_parent_cycle(): void
    {
        $this->declare('Anc\A', "namespace Anc;\n\nclass A extends B\n{\n}");
        $this->declare('Anc\B', "namespace Anc;\n\nclass B extends A\n{\n}");

        self::assertFalse($this->ancestry()->rulesOut('Anc\A'));
    }

    public function test_does_not_rule_out_a_located_file_that_is_gone(): void
    {
        $this->files['Anc\Gone'] = $this->dir . '/Gone.php';

        self::assertFalse($this->ancestry()->rulesOut('Anc\Gone'));
    }

    public function test_does_not_rule_out_abstract_facade_itself(): void
    {
        self::assertFalse($this->ancestry()->rulesOut(\Gacela\Framework\AbstractFacade::class));
    }

    public function test_rules_out_a_loaded_class_that_is_not_a_facade(): void
    {
        self::assertTrue($this->ancestry()->rulesOut(self::class));
    }

    public function test_the_registered_autoloaders_locate_a_project_class(): void
    {
        $ancestry = FacadeAncestry::fromRegisteredAutoloaders();

        self::assertTrue($ancestry->rulesOut(\GacelaTest\Unit\Console\Domain\AllAppModules\Fixtures\NotLoaded\ExtendsNothing::class));
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
        $files = $this->files;

        return new FacadeAncestry(static fn (string $className): ?string => $files[$className] ?? null);
    }
}
