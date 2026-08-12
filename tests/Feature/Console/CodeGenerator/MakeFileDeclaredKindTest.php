<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CodeGenerator;

use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * `make:file` generating a kind the project declared, from the stub the project
 * published for it.
 *
 * The two halves are both necessary and neither is enough: the declaration is
 * what stops the word being fuzzy-matched into a pillar, and the published stub
 * is the only template there can be -- a kind Gacela never heard of ships with
 * nothing to generate from.
 */
final class MakeFileDeclaredKindTest extends TestCase
{
    /**
     * `Psr4CodeGeneratorData\ => data/` in this directory's composer.json, and
     * the generator writes relative to the working directory, so the module
     * lands in the repository's own `data/`.
     */
    private const GENERATED_DIR = __DIR__ . '/../../../../data/DeclaredKindModule';

    private const MODULE_PATH = 'Psr4CodeGeneratorData/DeclaredKindModule';

    private string $stubsDir = '';

    protected function setUp(): void
    {
        $this->stubsDir = sys_get_temp_dir() . '/gacela-declared-kind-' . bin2hex(random_bytes(6));
        mkdir($this->stubsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir(self::GENERATED_DIR);

        if ($this->stubsDir !== '') {
            DirectoryUtil::removeDir($this->stubsDir);
        }

        Gacela::resetCache();
    }

    public function test_it_generates_a_declared_kind_from_the_published_stub(): void
    {
        $this->bootstrapDeclaring('Exporter');
        $this->publishStub('exporter-maker.txt', "<?php\n\nnamespace \$NAMESPACE\$;\n\nfinal class \$CLASS_NAME\$\n{\n    // house style exporter for \$MODULE_NAME\$\n}\n");

        $tester = $this->makeFile('Exporter');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists(self::GENERATED_DIR . '/DeclaredKindModuleExporter.php');

        $generated = (string)file_get_contents(self::GENERATED_DIR . '/DeclaredKindModuleExporter.php');

        self::assertStringContainsString('namespace Psr4CodeGeneratorData\DeclaredKindModule;', $generated);
        self::assertStringContainsString('final class DeclaredKindModuleExporter', $generated);
        self::assertStringContainsString('// house style exporter for DeclaredKindModule', $generated);
    }

    public function test_the_short_name_drops_the_module_prefix_from_a_declared_kind(): void
    {
        $this->bootstrapDeclaring('Exporter');
        $this->publishStub('exporter-maker.txt', 'namespace $NAMESPACE$; class $CLASS_NAME$ {}');

        $tester = $this->makeFile('Exporter', shortName: true);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists(self::GENERATED_DIR . '/Exporter.php');
        self::assertFileDoesNotExist(self::GENERATED_DIR . '/DeclaredKindModuleExporter.php');
    }

    /**
     * A declared kind has no built-in template to fall back on, so the only
     * useful answer names the file the project still has to write.
     */
    public function test_a_declared_kind_without_a_published_stub_names_the_file_to_write(): void
    {
        $this->bootstrapDeclaring('Exporter');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($this->stubsDir . '/exporter-maker.txt');

        $this->makeFile('Exporter');
    }

    /**
     * Undeclared, `Exporter` is just another string to fuzzy-match, and it
     * matches whatever it matched before declared kinds existed. Pinned so that
     * teaching the sanitizer new kinds cannot quietly re-route the old ones.
     */
    public function test_an_undeclared_kind_still_resolves_to_the_closest_pillar(): void
    {
        $this->bootstrapWithoutDeclarations();
        $this->publishStub('exporter-maker.txt', 'namespace $NAMESPACE$; class $CLASS_NAME$ {}');

        $tester = $this->makeFile('Exporter');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists(self::GENERATED_DIR . '/DeclaredKindModuleProvider.php');
        self::assertFileDoesNotExist(self::GENERATED_DIR . '/DeclaredKindModuleExporter.php');
    }

    public function test_the_help_text_lists_the_declared_kind_next_to_the_pillars(): void
    {
        $this->bootstrapDeclaring('Exporter');

        $description = (new MakeFileCommand())->getDescription();

        self::assertStringStartsWith('Generate a ', $description);
        self::assertStringContainsString('Facade', $description);
        self::assertStringContainsString('Provider', $description);
        self::assertStringContainsString('Exporter', $description);
    }

    public function test_the_help_text_lists_only_the_pillars_without_a_declaration(): void
    {
        $this->bootstrapWithoutDeclarations();

        self::assertStringNotContainsString('Exporter', (new MakeFileCommand())->getDescription());
    }

    private function makeFile(string $filename, bool $shortName = false): CommandTester
    {
        $tester = new CommandTester(new MakeFileCommand());
        $tester->execute([
            'path' => self::MODULE_PATH,
            'filenames' => [$filename],
            '--short-name' => $shortName,
        ]);

        return $tester;
    }

    private function bootstrapDeclaring(string $kind): void
    {
        $stubsDir = $this->stubsDir;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($kind, $stubsDir): void {
            $config->resetInMemoryCache();
            $config->addResolvableType($kind);
            $config->setStubsDir($stubsDir);
        });
    }

    private function bootstrapWithoutDeclarations(): void
    {
        $stubsDir = $this->stubsDir;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($stubsDir): void {
            $config->resetInMemoryCache();
            $config->setStubsDir($stubsDir);
        });
    }

    private function publishStub(string $filename, string $contents): void
    {
        file_put_contents($this->stubsDir . '/' . $filename, $contents);
    }
}
