<?php

declare(strict_types=1);

namespace Gacela\Framework\Shared;

final class FileIo implements FileIoInterface
{
    public function existsFile(string $filename): bool
    {
        return file_exists($filename);
    }

    /**
     * @return mixed
     */
    public function include(string $filename)
    {
        /** @psalm-suppress UnresolvableInclude */
        return include $filename;
    }

    public function fileGetContents(string $filename): string
    {
        return file_get_contents($filename); // @phpstan-ignore-line
    }

    /**
     * @param array<array-key,string>|resource|string|null $data The data to write.
     * Can be either a string, an array or a stream resource.
     */
    public function writeFile(string $filename, $data = null): void
    {
        if (null !== $data) {
            file_put_contents($filename, $data);
        } else {
            touch($filename);
        }
    }
}
