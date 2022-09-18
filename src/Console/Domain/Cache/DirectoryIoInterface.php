<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\Cache;

interface DirectoryIoInterface
{
    /**
     * @return list<string> the removed file paths
     */
    public function removeDir(string $target): array;
}
