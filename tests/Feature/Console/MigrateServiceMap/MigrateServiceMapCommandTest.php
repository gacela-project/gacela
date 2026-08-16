<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\MigrateServiceMap;

use Gacela\Console\Infrastructure\Command\MigrateServiceMapCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

/**
 * The command rewrites files in somebody's project, so the properties worth
 * asserting are the ones a user bets their working tree on: a preview writes
 * nothing, the preview names what the real run does, and a second run is a
 * no-op.
 */
final class MigrateServiceMapCommandTest extends TestCase
{
    private string $appRoot = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/gacela-migrate-' . bin2hex(random_bytes(6));
        mkdir($this->appRoot . '/Wallet', 0777, true);

        Gacela::bootstrap($this->appRoot, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setAppModulePaths(['.']);
        });
    }

    protected function tearDown(): void
    {
        // Names exactly what this test created.
        $this->removeRecursively($this->appRoot);
        Gacela::resetCache();
    }

    public function test_it_declares_the_accessor_and_leaves_valid_php(): void
    {
        $file = $this->writeUnmigratedClass();

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('WalletCommand::getFacade()', $tester->getDisplay());
        self::assertStringContainsString('Declared 1 accessor(s) in 1 file(s)', $tester->getDisplay());

        $migrated = (string)file_get_contents($file);

        self::assertStringContainsString(
            "#[ServiceMap(method: 'getFacade', className: WalletFacade::class)]",
            $migrated,
        );
        self::assertStringContainsString('use Gacela\Framework\ServiceResolver\ServiceMap;', $migrated);
        // The docblock the attribute was derived from is left where it was.
        self::assertStringContainsString('@method WalletFacade getFacade()', $migrated);
    }

    public function test_a_dry_run_reports_and_writes_nothing(): void
    {
        $file = $this->writeUnmigratedClass();
        $before = (string)file_get_contents($file);

        $tester = $this->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Would declare 1 accessor(s)', $tester->getDisplay());
        self::assertStringContainsString('Nothing was written', $tester->getDisplay());
        self::assertSame($before, (string)file_get_contents($file));
    }

    /**
     * Asserting the preview alone would pass for one that invented its list.
     */
    public function test_the_dry_run_names_the_accessors_the_real_run_declares(): void
    {
        $this->writeUnmigratedClass();

        $preview = $this->execute(['--dry-run' => true])->getDisplay();
        $real = $this->execute()->getDisplay();

        self::assertStringContainsString('WalletCommand::getFacade()', $preview);
        self::assertStringContainsString('WalletCommand::getFacade()', $real);
    }

    /**
     * A migration that could not be re-run would be one nobody could finish
     * after fixing whatever stopped it half way.
     */
    public function test_a_second_run_has_nothing_to_migrate(): void
    {
        $this->writeUnmigratedClass();
        $this->execute();

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Nothing to migrate', $tester->getDisplay());
    }

    public function test_a_project_with_nothing_to_migrate_says_so(): void
    {
        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Nothing to migrate', $tester->getDisplay());
    }

    /**
     * The argument narrows by path, so a migration can be taken one module at
     * a time rather than as one commit across a whole project.
     */
    public function test_the_filter_narrows_to_matching_paths(): void
    {
        $wallet = $this->writeUnmigratedClass();
        mkdir($this->appRoot . '/Invoice', 0777, true);
        $invoice = $this->appRoot . '/Invoice/InvoiceCommand.php';
        file_put_contents($invoice, $this->unmigratedClass('Invoice', 'InvoiceCommand', 'InvoiceFacade'));

        $tester = $this->execute(['filter' => 'Invoice']);

        self::assertStringContainsString('InvoiceCommand::getFacade()', $tester->getDisplay());
        self::assertStringNotContainsString('WalletCommand::getFacade()', $tester->getDisplay());
        self::assertStringNotContainsString('ServiceMap', (string)file_get_contents($wallet));
        self::assertStringContainsString('ServiceMap', (string)file_get_contents($invoice));
    }

    private function writeUnmigratedClass(): string
    {
        $file = $this->appRoot . '/Wallet/WalletCommand.php';
        file_put_contents($file, $this->unmigratedClass('Wallet', 'WalletCommand', 'WalletFacade'));

        return $file;
    }

    private function unmigratedClass(string $namespace, string $class, string $facade): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace MigrateFixture\\{$namespace};

            use Gacela\\Framework\\ServiceResolverAwareTrait;

            /**
             * @method {$facade} getFacade()
             */
            final class {$class}
            {
                use ServiceResolverAwareTrait;
            }

            PHP;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(array $input = []): CommandTester
    {
        $tester = new CommandTester(new MigrateServiceMapCommand());
        $tester->execute($input);

        return $tester;
    }

    private function removeRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeRecursively($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
