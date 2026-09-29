<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DtoGenerate;

use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function mkdir;
use function proc_close;
use function proc_open;
use function rmdir;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

/**
 * A gacela.php that needs a service only its host supplies cannot be read from
 * the command line. That has to fail naming the key, never pass as a project
 * with nothing declared.
 */
final class DtoGenerateBootstrapFailureTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-dto-boot-' . uniqid('', true);
        mkdir($this->projectRoot . DIRECTORY_SEPARATOR . 'vendor', 0o777, true);

        file_put_contents(
            $this->autoloadFile(),
            '<?php return require ' . var_export(dirname(__DIR__, 4) . '/vendor/autoload.php', true) . ';',
        );

        file_put_contents($this->gacelaFile(), <<<'PHP'
            <?php

            use Gacela\Framework\Bootstrap\GacelaConfig;
            use Gacela\Framework\Dto\Schema\DtoType;

            return static function (GacelaConfig $config): void {
                $config->addBinding('clock', $config->getExternalService('clock'));
                $config->declareDtoSchema('AcmeShop\Order', ['reference' => DtoType::string()]);
            };
            PHP);
    }

    protected function tearDown(): void
    {
        unlink($this->gacelaFile());
        unlink($this->autoloadFile());
        rmdir($this->projectRoot . DIRECTORY_SEPARATOR . 'vendor');
        rmdir($this->projectRoot);
    }

    public function test_a_missing_external_service_fails_naming_its_key(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runDtoGenerate();

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('External service "clock" not found', $stderr);
        self::assertStringNotContainsString('No shape declared', $stdout);
    }

    /**
     * @return array{int, string, string} exit code, stdout, stderr
     */
    private function runDtoGenerate(): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/gacela', 'dto:generate'],
            $descriptors,
            $pipes,
            $this->projectRoot,
        );
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    private function autoloadFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    }

    private function gacelaFile(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . 'gacela.php';
    }
}
