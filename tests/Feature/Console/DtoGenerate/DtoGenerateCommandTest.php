<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DtoGenerate;

use Gacela\Console\Infrastructure\Command\DtoGenerateCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Dto\MissingDtoPropertyException;
use Gacela\Framework\Dto\Schema\DtoType;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function is_dir;
use function is_file;
use function json_encode;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * Generates into a throwaway project, then requires the result and uses it.
 *
 * Asserting the generated *text* would be a golden master over characters
 * nobody reads. What the declaration promises is behaviour, so the test loads
 * the class php actually parsed and exercises it.
 */
final class DtoGenerateCommandTest extends TestCase
{
    private string $projectDir = '';

    /** Unique per test: the generated class is require()d, and php refuses a redeclaration. */
    private string $namespace = '';

    protected function setUp(): void
    {
        $unique = bin2hex(random_bytes(4));
        $this->projectDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-dto-' . $unique;
        $this->namespace = 'AcmeShop' . $unique;
        mkdir($this->projectDir . DIRECTORY_SEPARATOR . 'src', 0777, true);

        // Nameless on purpose: an application's root manifest is not published,
        // and a named one here would be reported by the package-manifest check.
        file_put_contents(
            $this->projectDir . DIRECTORY_SEPARATOR . 'composer.json',
            (string)json_encode(['autoload' => ['psr-4' => [$this->namespace . '\\' => 'src']]]),
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPaths() as $path) {
            self::assertStringStartsWith($this->projectDir . DIRECTORY_SEPARATOR, $path);
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach (['src' . DIRECTORY_SEPARATOR . 'Checkout', 'src', ''] as $relative) {
            $directory = $relative === ''
                ? $this->projectDir
                : $this->projectDir . DIRECTORY_SEPARATOR . $relative;

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function test_it_writes_the_class_where_the_projects_own_autoload_looks(): void
    {
        $tester = $this->generate();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists($this->orderFile());
    }

    /**
     * The whole point of generating rather than inferring: the file is ordinary
     * php that any parser reads.
     */
    public function test_the_generated_class_behaves_as_declared(): void
    {
        $this->generate();
        require_once $this->orderFile();

        /** @var class-string $class */
        $class = $this->orderClass();

        $order = $class::fromArray(['reference' => 'ord-1187', 'total' => 4990]);

        self::assertSame('ord-1187', $order->getReference());
        self::assertSame(4990, $order->getTotal());
        self::assertNull($order->getCouponCode(), 'an optional property nothing set reads as null');
        self::assertSame('EUR', $order->getCurrency(), 'a declared default is not missing');
    }

    public function test_a_with_method_returns_a_new_instance_and_leaves_the_first_alone(): void
    {
        $this->generate();
        require_once $this->orderFile();

        /** @var class-string $class */
        $class = $this->orderClass();

        $order = $class::fromArray(['reference' => 'ord-1187', 'total' => 4990]);
        $discounted = $order->withCouponCode('SUMMER10');

        self::assertSame('SUMMER10', $discounted->getCouponCode());
        self::assertNull($order->getCouponCode());
    }

    /**
     * What "omits what was never set" excludes, and what it does not. An
     * optional property nobody set has no value and is absent; one with a
     * declared default has a value, so it is present -- and only the round-trip
     * was asserted, which holds either way because the default would reapply.
     *
     * It shows in what a consumer sees: `toArray()` reaches JSON, and a reader
     * cannot tell a `currency` somebody chose from one nobody did.
     */
    public function test_an_array_carries_a_default_and_omits_a_property_with_no_value(): void
    {
        $this->generate();
        require_once $this->orderFile();

        /** @var class-string $class */
        $class = $this->orderClass();

        $array = $class::fromArray(['reference' => 'ord-1187', 'total' => 4990])->toArray();

        self::assertSame('EUR', $array['currency'] ?? null, 'a declared default is a value, so it is carried');
        self::assertArrayNotHasKey('couponCode', $array, 'optional with no default and never set: nothing to carry');
    }

    public function test_an_array_round_trips(): void
    {
        $this->generate();
        require_once $this->orderFile();

        /** @var class-string $class */
        $class = $this->orderClass();

        $order = $class::fromArray(['reference' => 'ord-1187', 'total' => 4990]);

        self::assertEquals($order, $class::fromArray($order->toArray()));
    }

    /**
     * A required getter is non-nullable, so it answers or it names the property
     * nobody set -- rather than handing back a null that fails somewhere else.
     */
    public function test_reading_a_required_property_nothing_set_names_it(): void
    {
        $this->generate();
        require_once $this->orderFile();

        /** @var class-string $class */
        $class = $this->orderClass();

        $this->expectException(MissingDtoPropertyException::class);
        $this->expectExceptionMessage('reference');

        $class::fromArray([])->getReference();
    }

    public function test_regenerating_an_unchanged_declaration_reports_it_up_to_date(): void
    {
        $this->generate();
        $tester = $this->generate();

        self::assertStringContainsString('already up to date', $tester->getDisplay());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $tester = $this->generate(['--dry-run' => true]);

        self::assertStringContainsString('would write', $tester->getDisplay());
        self::assertFileDoesNotExist($this->orderFile());
    }

    /**
     * A shape declared under a namespace the project never told composer about
     * has nowhere to live, and saying so beats writing it where nothing loads it.
     */
    public function test_a_shape_no_autoload_prefix_covers_fails_naming_it(): void
    {
        $tester = $this->generate(input: [], className: 'Nowhere\Checkout\Order');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Nowhere\Checkout\Order', $tester->getDisplay());
    }

    /**
     * A shape with nowhere to live is skipped, not a reason to stop: the shapes
     * after it still have to be written.
     */
    public function test_an_unplaceable_shape_does_not_stop_the_others(): void
    {
        // Sorted before the placeable shape, since shapes are processed in
        // sorted order -- otherwise skipping it and stopping at it coincide.
        $tester = $this->generateMany(['AAAUncovered\Checkout\Order', $this->orderClass()]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertFileExists($this->orderFile());
    }

    /**
     * The same for a shape whose file already says this.
     */
    public function test_an_unchanged_shape_does_not_stop_the_others(): void
    {
        // Invoice sorts before Order, so writing Invoice first makes it the
        // unchanged one the second run meets before it reaches Order.
        $this->generateMany([$this->namespace . '\Checkout\Invoice']);
        $tester = $this->generateMany([$this->namespace . '\Checkout\Invoice', $this->orderClass()]);

        self::assertStringContainsString('already up to date', $tester->getDisplay());
        self::assertFileExists($this->orderFile());
    }

    /**
     * The CI question this command could not answer: are the generated classes
     * in the repository up to date with what `gacela.php` declares? `--dry-run`
     * reports it and exits 0 either way, so a job had to parse the output.
     */
    public function test_check_fails_when_a_class_would_be_written(): void
    {
        $tester = $this->generate(['--check' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('would write', $tester->getDisplay());
    }

    public function test_check_writes_nothing_even_though_it_fails(): void
    {
        $this->generate(['--check' => true]);

        self::assertFileDoesNotExist($this->orderFile(), 'a check must not write what it is checking');
    }

    public function test_check_passes_once_the_classes_are_generated(): void
    {
        $this->generate();

        $tester = $this->generate(['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * With nothing declared there is nothing to be stale, which is a pass --
     * the same answer `--check` gives a project whose classes are current.
     */
    public function test_check_passes_when_no_shape_is_declared(): void
    {
        Gacela::bootstrap($this->projectDir, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });

        $tester = new CommandTester(new DtoGenerateCommand());
        $tester->execute(['--check' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * The command's own empty case: nothing declared is not a failure, and the
     * message has to name the call that would change that. Untested until now,
     * which is why `DtoGenerateResult::total()` had no coverage at all -- it is
     * read only here.
     */
    public function test_a_project_declaring_no_shape_is_told_how_to_declare_one(): void
    {
        Gacela::bootstrap($this->projectDir, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });

        $tester = new CommandTester(new DtoGenerateCommand());
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(
            'No shape declared. Use $config->declareDtoSchema(...) in gacela.php.',
            $tester->getDisplay(),
        );
    }

    /**
     * @param list<string> $classNames
     */
    private function generateMany(array $classNames): CommandTester
    {
        Gacela::bootstrap($this->projectDir, static function (GacelaConfig $config) use ($classNames): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);

            foreach ($classNames as $className) {
                $config->declareDtoSchema($className, [
                    'reference' => DtoType::string()->required(),
                    'total' => DtoType::int()->required()->describe('order total in cents'),
                    'couponCode' => DtoType::string(),
                    'currency' => DtoType::string()->default('EUR'),
                ]);
            }
        });

        $tester = new CommandTester(new DtoGenerateCommand());
        $tester->execute([]);

        return $tester;
    }

    /**
     * @return list<string>
     */
    private function createdPaths(): array
    {
        return [
            $this->projectDir . DIRECTORY_SEPARATOR . 'composer.json',
            $this->orderFile(),
            $this->projectDir . DIRECTORY_SEPARATOR . 'src'
                . DIRECTORY_SEPARATOR . 'Checkout' . DIRECTORY_SEPARATOR . 'Invoice.php',
        ];
    }

    private function orderClass(): string
    {
        return $this->namespace . '\\Checkout\\Order';
    }

    private function orderFile(): string
    {
        return $this->projectDir . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Checkout' . DIRECTORY_SEPARATOR . 'Order.php';
    }

    /**
     * @param array<string, bool> $input
     */
    private function generate(array $input = [], ?string $className = null): CommandTester
    {
        $className ??= $this->orderClass();
        $projectDir = $this->projectDir;

        Gacela::bootstrap($projectDir, static function (GacelaConfig $config) use ($className): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->declareDtoSchema($className, [
                'reference' => DtoType::string()->required(),
                'total' => DtoType::int()->required()->describe('order total in cents'),
                'couponCode' => DtoType::string(),
                'currency' => DtoType::string()->default('EUR'),
            ]);
        });

        $tester = new CommandTester(new DtoGenerateCommand());
        $tester->execute($input);

        return $tester;
    }
}
