<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\ConfigReader;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PhpConfigReaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        // Reading dispatches a ReadPhpConfigEvent, which needs a bootstrapped Config.
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        Gacela::resetCache();
    }

    public function test_reads_array_config_file(): void
    {
        $file = $this->createTempConfig('<?php return ["key" => "value"];');

        self::assertSame(['key' => 'value'], (new PhpConfigReader())->read($file));
    }

    public function test_reads_json_serializable_config_file(): void
    {
        $file = $this->createTempConfig(
            '<?php return new class() implements JsonSerializable {
                public function jsonSerialize(): array
                {
                    return ["from-json" => true];
                }
            };',
        );

        self::assertSame(['from-json' => true], (new PhpConfigReader())->read($file));
    }

    public function test_null_return_yields_empty_config(): void
    {
        $file = $this->createTempConfig('<?php return null;');

        self::assertSame([], (new PhpConfigReader())->read($file));
    }

    public function test_non_array_return_throws(): void
    {
        $file = $this->createTempConfig('<?php return 123;');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'The PHP config file "%s" must return an array or a JsonSerializable object!',
            $file,
        ));

        (new PhpConfigReader())->read($file);
    }

    /**
     * The shape a non-config file has: `include` returns 1 when a file returns
     * nothing, so a script dropped where the config glob matches it arrives here
     * looking exactly like a typo in a real config file. Naming the file is what
     * tells those two apart.
     */
    public function test_a_file_that_returns_nothing_throws_naming_it(): void
    {
        $file = $this->createTempConfig("<?php\n\nclass_exists(RuntimeException::class);\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($file);

        (new PhpConfigReader())->read($file);
    }

    public function test_unreadable_path_yields_empty_config(): void
    {
        self::assertSame([], (new PhpConfigReader())->read('/nonexistent/config.php'));
    }

    private function createTempConfig(string $content): string
    {
        $file = sys_get_temp_dir() . '/gacela-php-config-' . uniqid('', true) . '.php';
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }
}
