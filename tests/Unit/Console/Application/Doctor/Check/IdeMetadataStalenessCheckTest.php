<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\IdeMetadataStalenessCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\IdeMeta\IdeMetadataPath;
use Gacela\Console\Domain\IdeMeta\IdeMetadataResult;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function is_dir;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class IdeMetadataStalenessCheckTest extends TestCase
{
    private string $appRootDir = '';

    protected function setUp(): void
    {
        $this->appRootDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-ide-meta-' . bin2hex(random_bytes(4));
        mkdir($this->appRootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Named from the property this test set, and only the two entries it
        // could have created.
        $file = IdeMetadataPath::fileIn($this->appRootDir);
        if (is_file($file)) {
            unlink($file);
        }

        $directory = IdeMetadataPath::directoryIn($this->appRootDir);
        if (is_dir($directory)) {
            rmdir($directory);
        }

        if (is_dir($this->appRootDir)) {
            rmdir($this->appRootDir);
        }
    }

    /**
     * A project that never ran `ide:meta` must not pay for an application-wide
     * module scan to be told about a file it does not have.
     */
    public function test_without_a_generated_file_nothing_is_regenerated(): void
    {
        $regenerated = false;

        $result = (new IdeMetadataStalenessCheck(
            $this->appRootDir,
            function () use (&$regenerated): IdeMetadataResult {
                $regenerated = true;
                return $this->metadataResult(changed: false);
            },
        ))->run();

        self::assertFalse($regenerated);
        self::assertSame(CheckStatus::Ok, $result->status);
    }

    public function test_a_file_matching_the_attributes_passes(): void
    {
        $this->writeMetadata();

        $result = (new IdeMetadataStalenessCheck($this->appRootDir, fn (): IdeMetadataResult => $this->metadataResult(changed: false)))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    public function test_a_file_the_attributes_no_longer_produce_warns_and_names_the_fix(): void
    {
        $this->writeMetadata();

        $result = (new IdeMetadataStalenessCheck($this->appRootDir, fn (): IdeMetadataResult => $this->metadataResult(changed: true)))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('ide:meta', $result->remediation);
        self::assertStringContainsString(IdeMetadataPath::fileIn($this->appRootDir), $result->details[0]);
        self::assertStringContainsString('no longer matches', $result->details[0]);
    }

    private function writeMetadata(): void
    {
        mkdir(IdeMetadataPath::directoryIn($this->appRootDir), 0777, true);
        file_put_contents(IdeMetadataPath::fileIn($this->appRootDir), '<?php');
    }

    private function metadataResult(bool $changed): IdeMetadataResult
    {
        return new IdeMetadataResult(
            path: IdeMetadataPath::fileIn($this->appRootDir),
            content: '<?php',
            changed: $changed,
            written: false,
            typedIds: 0,
        );
    }
}
