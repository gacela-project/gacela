<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ModuleDirectoryHint;
use PHPUnit\Framework\TestCase;
use stdClass;

use function assert;
use function bin2hex;
use function glob;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

final class ModuleDirectoryHintTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            // Each entry was built by makeModuleDirectory() below, under the
            // system temp dir and under a name this test generated -- so the
            // sweep names only what the test created. Asserted rather than
            // assumed, because a glob in cleanup code is the one place a wrong
            // root is not noticed.
            assert($directory !== '' && str_starts_with($directory, sys_get_temp_dir()));

            foreach (glob($directory . '/*.php') ?: [] as $file) {
                unlink($file);
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        $this->directories = [];
    }

    /**
     * The case the hint exists for: the class is written, and named something
     * no finder rule builds. Nothing in the message pointed at it before, so
     * "add the missing Provider" asked for a file already on disk.
     */
    public function test_a_file_extending_the_base_class_under_another_name_is_reported(): void
    {
        $caller = $this->moduleWith('Typo', [
            'TypoFactory' => 'extends \\Gacela\\Framework\\AbstractFactory',
            'TypoProvidr' => 'extends \\Gacela\\Framework\\AbstractProvider',
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Typo\\TypoProvider']);

        self::assertSame(
            ['TypoProvidr.php extends AbstractProvider, under a name none of'
                . ' the candidates above has -- rename it to one of them'],
            $hints,
        );
    }

    /**
     * The other half: the name is right, so the reader is told the file is
     * fine and the namespace inside it is not -- rather than being sent to
     * write a file that is already there.
     */
    public function test_a_file_named_as_expected_that_does_not_declare_it_is_reported(): void
    {
        $caller = $this->moduleWith('Mismatch', [
            'MismatchFactory' => 'extends \\Gacela\\Framework\\AbstractFactory',
            'MismatchProvider' => '',
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Mismatch\\MismatchProvider']);

        self::assertSame(
            ['MismatchProvider.php does not declare `X\\Mismatch\\MismatchProvider`'
                . ' -- check the namespace it declares'],
            $hints,
        );
    }

    /**
     * The candidate carries the leading separator the message prints it with;
     * the hint quotes a class name, where that separator is noise.
     */
    public function test_the_reported_class_name_drops_the_leading_separator(): void
    {
        $caller = $this->moduleWith('Leading', ['LeadingProvider' => '']);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Leading\\LeadingProvider']);

        self::assertStringContainsString('`X\\Leading\\LeadingProvider`', $hints[0]);
        self::assertStringNotContainsString('`\\X\\Leading\\LeadingProvider`', $hints[0]);
    }

    /**
     * The reason this reads the file rather than comparing names: every pillar
     * of a module shares its prefix, so a similarity threshold loose enough to
     * catch `TypoProvidr` also offers `TypoFacade` as the missing Provider.
     */
    public function test_the_other_pillars_of_the_module_are_not_offered(): void
    {
        $caller = $this->moduleWith('Sibling', [
            'SiblingFacade' => 'extends \\Gacela\\Framework\\AbstractFacade',
            'SiblingFactory' => 'extends \\Gacela\\Framework\\AbstractFactory',
            'SiblingConfig' => 'extends \\Gacela\\Framework\\AbstractConfig',
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Sibling\\SiblingProvider']);

        self::assertSame([], $hints);
    }

    /**
     * The kind asked about decides the base class looked for, so a sibling
     * extending a different one is not the answer.
     */
    public function test_a_file_extending_a_different_base_class_is_not_reported(): void
    {
        $caller = $this->moduleWith('Custom', [
            'CustomExportr' => 'extends \\Gacela\\Framework\\AbstractProvider',
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Exporter', ['\\X\\Custom\\CustomExporter']);

        self::assertSame([], $hints);
    }

    /**
     * A kind declared through `addResolvableType()` is asked the same question
     * as the four pillars: the base class is read out of the file rather than
     * assumed to be one of Gacela's, so a project that gave its own kind a base
     * gets the same hint.
     */
    public function test_a_declared_kind_with_a_base_class_of_its_own_is_reported(): void
    {
        $caller = $this->moduleWith('Declared', [
            'DeclaredFactory' => 'extends \\Gacela\\Framework\\AbstractFactory',
            'DeclaredExportr' => 'extends AbstractExporter',
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Exporter', ['\\X\\Declared\\DeclaredExporter']);

        self::assertCount(1, $hints);
        self::assertStringContainsString('DeclaredExportr.php extends AbstractExporter', $hints[0]);
    }

    /**
     * The parent name arrives as one token either way, and both spellings are
     * ordinary: the scaffolder writes the imported one.
     */
    public function test_the_imported_spelling_of_the_base_class_is_recognised(): void
    {
        $caller = $this->moduleWithSources('Imported', [
            'ImportedFactory' => "class ImportedFactory\n{\n}\n",
            'ImportedProvidr' => "use Gacela\\Framework\\AbstractProvider;\n\n"
                . "class ImportedProvidr extends AbstractProvider\n{\n}\n",
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Imported\\ImportedProvider']);

        self::assertCount(1, $hints);
        self::assertStringContainsString('ImportedProvidr.php', $hints[0]);
    }

    /**
     * The scan stops treating a name as a parent once it has read one. Without
     * that, `extends Base` leaves the reader armed and the next class name in
     * the file -- a parameter type, a `new`, an import -- is taken for a parent.
     */
    public function test_a_base_class_named_only_as_a_parameter_type_is_not_a_parent(): void
    {
        $caller = $this->moduleWithSources('Hinted', [
            'HintedFactory' => "class HintedFactory\n{\n}\n",
            'HintedThing' => "use Gacela\\Framework\\AbstractProvider;\n\n"
                . "class HintedThing extends \\ArrayObject\n{\n"
                . "    public function take(AbstractProvider \$provider): void\n    {\n    }\n}\n",
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Hinted\\HintedProvider']);

        self::assertSame([], $hints);
    }

    /**
     * Nothing is a parent until `extends` says so.
     *
     * A file with no namespace opens on its imports, so the base class is the
     * first name in the token stream. Reading names as parents before seeing
     * `extends` reports this file -- which extends `ArrayObject` and only
     * imports the base class -- as the missing Provider.
     */
    public function test_a_name_before_any_extends_is_not_a_parent(): void
    {
        $caller = $this->moduleWithSources('Unnamespaced', [
            'UnnamespacedFactory' => "class UnnamespacedFactory\n{\n}\n",
            'UnnamespacedThing' => "<?php\n\nuse Gacela\\Framework\\AbstractProvider;\n\n"
                . "class UnnamespacedThing extends \\ArrayObject\n{\n}\n",
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Unnamespaced\\UnnamespacedProvider']);

        self::assertSame([], $hints);
    }

    /**
     * ...and a second class in the same file is still read, so resetting after
     * a non-matching parent does not stop the scan.
     */
    public function test_a_second_class_in_the_file_is_still_read(): void
    {
        $caller = $this->moduleWithSources('Second', [
            'SecondFactory' => "class SecondFactory\n{\n}\n",
            'SecondPair' => "use Gacela\\Framework\\AbstractProvider;\n\n"
                . "class SecondPairOne extends \\ArrayObject\n{\n}\n\n"
                . "class SecondPairTwo extends AbstractProvider\n{\n}\n",
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Second\\SecondProvider']);

        self::assertCount(1, $hints);
        self::assertStringContainsString('SecondPair.php', $hints[0]);
    }

    /**
     * ...but the name check still applies to it, because that one compares
     * against the candidates the finder really tried and needs no base class.
     */
    public function test_a_kind_with_no_base_class_still_reports_the_expected_file_name(): void
    {
        $caller = $this->moduleWith('CustomNamed', ['CustomNamedExporter' => '']);

        $hints = ModuleDirectoryHint::findNear($caller, 'Exporter', ['\\X\\CustomNamed\\CustomNamedExporter']);

        self::assertCount(1, $hints);
        self::assertStringContainsString('CustomNamedExporter.php does not declare', $hints[0]);
    }

    /**
     * An exception message is read, not paged. Three names the reader can
     * check; a directory of near-misses is a listing, and the one that matters
     * is no easier to find there than in the directory itself.
     */
    public function test_it_reports_at_most_three_files(): void
    {
        $caller = $this->moduleWith('Many', [
            'ManyProvidr' => 'extends \\Gacela\\Framework\\AbstractProvider',
            'ManyProvder' => 'extends \\Gacela\\Framework\\AbstractProvider',
            'ManyProvidor' => 'extends \\Gacela\\Framework\\AbstractProvider',
            'ManyPovider' => 'extends \\Gacela\\Framework\\AbstractProvider',
        ]);

        $hints = ModuleDirectoryHint::findNear($caller, 'Provider', ['\\X\\Many\\ManyProvider']);

        self::assertCount(3, $hints);
    }

    /**
     * The directory is read off the caller, and an internal class has no file
     * to read it off. Nothing to say beats a hint about whichever directory a
     * fallback picked.
     */
    public function test_a_caller_with_no_file_yields_nothing(): void
    {
        self::assertSame([], ModuleDirectoryHint::findNear(new stdClass(), 'Provider', ['\\X\\Y\\YProvider']));
    }

    /**
     * The caller may be a string naming a class that was never loadable --
     * that is one of the ways resolution fails in the first place.
     */
    public function test_a_caller_that_is_not_a_loadable_class_yields_nothing(): void
    {
        /** @var class-string $missing */
        $missing = 'GacelaTest\\Unit\\Framework\\ClassResolver\\NoSuchCallerAnywhere';

        self::assertSame([], ModuleDirectoryHint::findNear($missing, 'Provider', ['\\X\\Y\\YProvider']));
    }

    /**
     * The common shape: one empty class per file, named after its file.
     *
     * @param array<string,string> $classes short class name => the `extends` clause, if any
     *
     * @return class-string
     */
    private function moduleWith(string $moduleName, array $classes): string
    {
        $sources = [];

        foreach ($classes as $shortName => $extends) {
            $sources[$shortName] = sprintf("class %s %s\n{\n}\n", $shortName, $extends);
        }

        return $this->moduleWithSources($moduleName, $sources);
    }

    /**
     * Builds a module directory and loads its first class, so reflection on the
     * returned caller points back at the directory. The classes are written at
     * run time rather than committed: one of them declares a namespace that
     * does not match its path, which is the fault being reported and not
     * something to leave in the tree for a scanner to find.
     *
     * Only the first file is loaded. The rest are read as text by the code
     * under test, which is what lets them be things PHP would refuse -- a class
     * named unlike its file, or one extending a base that does not exist.
     *
     * @param array<string,string> $sources file name without extension => the body below the namespace
     *
     * @return class-string
     */
    private function moduleWithSources(string $moduleName, array $sources): string
    {
        self::assertNotSame([], $sources, 'a module needs at least one class to reflect on');

        $directory = sys_get_temp_dir() . '/gacela-hint-' . bin2hex(random_bytes(4));
        mkdir($directory, 0777, true);
        $this->directories[] = $directory;

        $namespace = 'GacelaHintFixture' . $moduleName;
        $caller = null;

        foreach ($sources as $fileName => $body) {
            $file = $directory . '/' . $fileName . '.php';

            // A body that opens the tag itself is written verbatim, so a test
            // can write a file with no namespace at all.
            file_put_contents($file, str_starts_with($body, '<?php')
                ? $body
                : sprintf("<?php\n\nnamespace %s;\n\n%s", $namespace, $body));

            if ($caller === null) {
                require $file;
                /** @var class-string $caller */
                $caller = $namespace . '\\' . $fileName;
            }
        }

        self::assertNotNull($caller);

        return $caller;
    }
}
