<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\IdeMeta;

use Gacela\Console\Domain\IdeMeta\IdeMetadataPath;
use Gacela\Console\Infrastructure\Command\IdeMetaCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\IdeMeta\Billing\BillingProvider;
use GacelaTest\Feature\Console\IdeMeta\Billing\InvoiceSender;
use GacelaTest\Feature\Console\IdeMeta\Report\ReportProvider;
use GacelaTest\Feature\Console\IdeMeta\Report\ReportRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function file_get_contents;
use function is_dir;
use function is_file;
use function rmdir;
use function unlink;

final class IdeMetaCommandTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    protected function tearDown(): void
    {
        // Only the two entries the command can create, both named from the same
        // constants the command writes through, under this test's own directory.
        $file = IdeMetadataPath::fileIn(__DIR__);
        if (is_file($file)) {
            unlink($file);
        }

        $directory = IdeMetadataPath::directoryIn(__DIR__);
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    public function test_it_writes_a_map_typing_each_string_id_by_its_provider_method(): void
    {
        $tester = $this->ideMeta();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $written = file_get_contents(IdeMetadataPath::fileIn(__DIR__));

        self::assertStringContainsString(
            "'" . BillingProvider::INVOICE_SENDER . "' => \\" . InvoiceSender::class . '::class,',
            (string) $written,
        );
        self::assertStringContainsString(
            "'" . ReportProvider::REPORT_RENDERER . "' => \\" . ReportRenderer::class . '::class,',
            (string) $written,
        );
    }

    public function test_the_wildcard_covers_every_id_that_names_a_class(): void
    {
        $this->ideMeta();

        self::assertStringContainsString("'' => '@',", (string)file_get_contents(IdeMetadataPath::fileIn(__DIR__)));
    }

    /**
     * The map is keyed by argument value across the whole application, while
     * `getProvidedDependency()` reads the calling module's own container. An id
     * two modules type differently therefore has no single right answer, and a
     * wrong one would type-check a call that fails.
     */
    public function test_an_id_two_modules_type_differently_is_reported_and_left_out(): void
    {
        $tester = $this->ideMeta();

        self::assertStringContainsString(BillingProvider::SHARED_ID, $tester->getDisplay());
        self::assertStringContainsString(InvoiceSender::class, $tester->getDisplay());
        self::assertStringContainsString(ReportRenderer::class, $tester->getDisplay());

        self::assertStringNotContainsString(
            "'" . BillingProvider::SHARED_ID . "' =>",
            (string)file_get_contents(IdeMetadataPath::fileIn(__DIR__)),
        );
    }

    /**
     * A map value is a class name, so an `array` return has nothing truthful to
     * put there.
     */
    public function test_an_id_whose_provider_returns_no_class_is_left_out(): void
    {
        $this->ideMeta();

        self::assertStringNotContainsString("'COMMANDS'", (string)file_get_contents(IdeMetadataPath::fileIn(__DIR__)));
    }

    public function test_a_dry_run_reports_the_change_and_writes_nothing(): void
    {
        $tester = $this->ideMeta(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('would change', $tester->getDisplay());
        self::assertFileDoesNotExist(IdeMetadataPath::fileIn(__DIR__));
    }

    /**
     * Rewriting identical bytes would move the modification time of a file an
     * editor watches, and would make the doctor check unable to tell a real
     * change from a regeneration.
     */
    public function test_regenerating_an_unchanged_application_reports_up_to_date(): void
    {
        $this->ideMeta();
        $tester = $this->ideMeta();

        self::assertStringContainsString('is up to date', $tester->getDisplay());
    }

    public function test_a_dry_run_over_an_up_to_date_file_reports_it_up_to_date(): void
    {
        $this->ideMeta();
        $tester = $this->ideMeta(['--dry-run' => true]);

        self::assertStringContainsString('is up to date', $tester->getDisplay());
        self::assertFileExists(IdeMetadataPath::fileIn(__DIR__));
    }

    /**
     * @param array<string, bool> $input
     */
    private function ideMeta(array $input = []): CommandTester
    {
        $tester = new CommandTester(new IdeMetaCommand());
        $tester->execute($input);

        return $tester;
    }
}
