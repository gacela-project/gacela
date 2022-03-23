<?php

declare(strict_types=1);

namespace Gacela\Framework\Shared;

interface FileIoInterface
{
    public function existsFile(string $filename): bool;

    /**
     * @return mixed
     */
    public function include(string $filename);

    /**
     * @param array<array-key,string>|resource|string|null $data The data to write.
     * Can be either a string, an array or a stream resource.
     */
    public function writeFile(string $filename, $data): void;
}
