<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure;

use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use RuntimeException;

use function file_exists;
use function sprintf;
use function strlen;

final class FileContentIo implements FileContentIoInterface
{
    public function mkdir(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        // Recursive: the first module of a new project has no src/ parent yet,
        // so the documented `init` then `make:module` flow created nothing.
        if (mkdir($directory, recursive: true)) {
            return;
        }

        if (is_dir($directory)) {
            return;
        }

        throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
    }

    public function existsFile(string $path): bool
    {
        return file_exists($path);
    }

    public function filePutContents(string $path, string $fileContent): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        try {
            if (@file_put_contents($temporary, $fileContent) !== strlen($fileContent)
                || !$this->copyPermissions($path, $temporary)
                || !@rename($temporary, $path)
            ) {
                throw new RuntimeException(sprintf('File "%s" was not written; it was left as it was', $path));
            }
        } finally {
            @unlink($temporary);
        }
    }

    private function copyPermissions(string $path, string $temporary): bool
    {
        if (DIRECTORY_SEPARATOR === '\\' || !is_file($path)) {
            return true;
        }

        $permissions = @fileperms($path);

        return $permissions !== false && @chmod($temporary, $permissions & 0777);
    }
}
