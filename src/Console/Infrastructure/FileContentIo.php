<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure;

use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Override;
use RuntimeException;

use function sprintf;

/**
 * @codeCoverageIgnore
 */
final class FileContentIo implements FileContentIoInterface
{
    #[Override]
    public function mkdir(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (mkdir($directory)) {
            return;
        }

        if (is_dir($directory)) {
            return;
        }

        throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
    }

    #[Override]
    public function filePutContents(string $path, string $fileContent): void
    {
        file_put_contents($path, $fileContent);
    }
}
