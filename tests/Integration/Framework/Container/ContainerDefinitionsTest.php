<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Container\Exception\ContainerException;
use Gacela\Framework\Container\Container;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_file;
use function json_encode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * `load()` and `loadFile()` describe wiring as data rather than as a closure.
 *
 * The loader upstream is an implementation detail, so these do not re-test what
 * it does with each definition key. They pin the two things the decorator owns:
 * that both methods are reachable at all, and that a definition ends up in the
 * *decorated* container -- the one a Provider is handed -- rather than in an
 * inner container nobody can read from.
 */
final class ContainerDefinitionsTest extends TestCase
{
    /** @var list<string> */
    private array $writtenFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->writtenFiles = [];
    }

    public function test_load_registers_a_definition_on_the_decorator(): void
    {
        $container = new Container();

        $container->load([
            StringValueInterface::class => StringValue::class,
        ]);

        self::assertInstanceOf(StringValueInterface::class, $container->get(StringValueInterface::class));
    }

    public function test_load_registers_a_plain_value(): void
    {
        $container = new Container();

        $container->load([
            'db.dsn' => ['value' => 'pgsql://localhost/app'],
        ]);

        self::assertSame('pgsql://localhost/app', $container->get('db.dsn'));
    }

    /**
     * The point of the data layer: a later load overrides an earlier key, which
     * is what makes a per-environment override file work.
     */
    public function test_a_later_definition_overrides_an_earlier_one(): void
    {
        $container = new Container();

        $container->load(['db.dsn' => ['value' => 'first']]);
        $container->load(['db.dsn' => ['value' => 'second']]);

        self::assertSame('second', $container->get('db.dsn'));
    }

    public function test_load_file_reads_a_json_file(): void
    {
        $file = $this->writeFile('json', (string)json_encode([
            'db.dsn' => ['value' => 'pgsql://from-json/app'],
        ]));

        $container = new Container();
        $container->loadFile($file);

        self::assertSame('pgsql://from-json/app', $container->get('db.dsn'));
    }

    public function test_load_file_reads_a_php_file(): void
    {
        $file = $this->writeFile('php', "<?php return ['db.dsn' => ['value' => 'pgsql://from-php/app']];");

        $container = new Container();
        $container->loadFile($file);

        self::assertSame('pgsql://from-php/app', $container->get('db.dsn'));
    }

    /**
     * A missing file is the failure mode a relative path produces, and it must
     * be loud: the whole risk of describing wiring as data is a file that
     * silently does not arrive.
     */
    public function test_load_file_throws_when_the_file_is_missing(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);

        $container->loadFile(sys_get_temp_dir() . '/gacela-definitions-does-not-exist.json');
    }

    private function writeFile(string $extension, string $contents): string
    {
        $file = sys_get_temp_dir() . '/' . uniqid('gacela-definitions-', true) . '.' . $extension;
        file_put_contents($file, $contents);
        $this->writtenFiles[] = $file;

        return $file;
    }
}
