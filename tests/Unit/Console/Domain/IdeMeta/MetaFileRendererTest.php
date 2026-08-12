<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\IdeMeta;

use Gacela\Console\Domain\IdeMeta\MetaFileRenderer;
use Gacela\Console\Domain\IdeMeta\ProvidedDependencyMap;
use Gacela\Framework\AbstractFactory;
use PHPUnit\Framework\TestCase;
use stdClass;

use function sprintf;

final class MetaFileRendererTest extends TestCase
{
    public function test_the_file_declares_the_metadata_namespace_php_storm_reads(): void
    {
        $rendered = $this->render(new ProvidedDependencyMap());

        self::assertStringStartsWith('<?php', $rendered);
        self::assertStringContainsString('namespace PHPSTORM_META;', $rendered);
    }

    /**
     * The whole of what both analysers type, and the only part that needs no
     * scan to know -- so it is written whether or not anything else was found.
     */
    public function test_the_wildcard_is_written_even_for_an_empty_map(): void
    {
        $rendered = $this->render(new ProvidedDependencyMap());

        self::assertStringContainsString(
            'override(\\' . AbstractFactory::class . '::getProvidedDependency(0), map([',
            $rendered,
        );
        self::assertStringContainsString("'' => '@',", $rendered);
    }

    public function test_a_string_id_is_written_as_a_class_constant_reference(): void
    {
        $rendered = $this->render(new ProvidedDependencyMap(['BILLING' => stdClass::class]));

        // sprintf rather than concatenation: the leading backslash is the point
        // of the assertion, and the formatter folds `'\' . stdClass::class` into
        // the unqualified name.
        self::assertStringContainsString(sprintf("'BILLING' => \\%s::class,", stdClass::class), $rendered);
    }

    /**
     * Discovery order is a directory listing. Sorting is what lets the doctor
     * check compare content at all: without it an unchanged application
     * regenerates a different file.
     */
    public function test_ids_are_written_in_a_stable_order(): void
    {
        $oneWay = $this->render(new ProvidedDependencyMap(['B' => stdClass::class, 'A' => stdClass::class]));
        $other = $this->render(new ProvidedDependencyMap(['A' => stdClass::class, 'B' => stdClass::class]));

        self::assertSame($oneWay, $other);
        self::assertLessThan(strpos($oneWay, "'B'"), strpos($oneWay, "'A'"));
    }

    /**
     * Ids come out of user code, and the only guarantee that matters for a file
     * nothing executes is that it parses.
     */
    public function test_an_id_containing_a_quote_is_escaped(): void
    {
        $rendered = $this->render(new ProvidedDependencyMap(["it's" => stdClass::class]));

        self::assertStringContainsString(sprintf("'it\\'s' => \\%s::class,", stdClass::class), $rendered);
    }

    public function test_the_rendered_file_is_valid_php(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'gacela-meta-') . '.php';
        file_put_contents($file, $this->render(new ProvidedDependencyMap(["it's" => stdClass::class])));

        exec(PHP_BINARY . ' -l ' . escapeshellarg($file), $output, $exitCode);
        unlink($file);

        self::assertSame(0, $exitCode, implode("\n", $output));
    }

    private function render(ProvidedDependencyMap $map): string
    {
        return (new MetaFileRenderer())->render($map);
    }
}
