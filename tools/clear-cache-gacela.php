<?php

declare(strict_types=1);

$targets = ['gacela-custom-services.php', 'gacela-class-names.php'];
$root = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tests';

if (!is_dir($root)) {
    exit(0);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    if (\in_array($fileInfo->getFilename(), $targets, true)) {
        @unlink($fileInfo->getPathname());
    }
}
