<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FileContent;

use Gacela\Console\Domain\FileContent\JsonFile;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function is_file;
use function random_bytes;
use function sys_get_temp_dir;

final class JsonFileTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-json-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        self::assertStringStartsWith(sys_get_temp_dir() . DIRECTORY_SEPARATOR, $this->file);

        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function test_a_file_that_is_not_there_reads_as_nothing(): void
    {
        self::assertNull(JsonFile::decode($this->file));
    }

    public function test_malformed_json_reads_as_nothing(): void
    {
        file_put_contents($this->file, '{not json');

        self::assertNull(JsonFile::decode($this->file));
    }

    /**
     * A manifest is an object. A bare scalar is valid json and still not
     * something a caller here can read.
     */
    public function test_json_that_is_not_an_array_reads_as_nothing(): void
    {
        file_put_contents($this->file, '"just a string"');

        self::assertNull(JsonFile::decode($this->file));
    }

    public function test_an_object_is_decoded(): void
    {
        file_put_contents($this->file, '{"name":"acme/thing","require":{"php":">=8.3"}}');

        self::assertSame(
            ['name' => 'acme/thing', 'require' => ['php' => '>=8.3']],
            JsonFile::decode($this->file),
        );
    }

    public function test_an_empty_object_decodes_to_an_empty_array(): void
    {
        file_put_contents($this->file, '{}');

        self::assertSame([], JsonFile::decode($this->file));
    }
}
