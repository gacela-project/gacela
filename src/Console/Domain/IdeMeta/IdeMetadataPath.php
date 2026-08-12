<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\IdeMeta;

/**
 * Where the generated metadata lives, asked once by everything that reads or
 * writes it.
 *
 * The directory form of `.phpstorm.meta.php` rather than the file form: a
 * project that keeps its own hand-written root meta file must not have it
 * overwritten, and PhpStorm reads every file in the directory.
 *
 * It belongs in the project tree because that is the only place an editor
 * looks. The cache directory is not an option -- with no file cache configured
 * it defaults to the system temp directory.
 */
final class IdeMetadataPath
{
    public const DIRECTORY = '.phpstorm.meta.php';

    public const FILENAME = 'gacela.meta.php';

    public static function directoryIn(string $appRootDir): string
    {
        return rtrim($appRootDir, '/\\') . DIRECTORY_SEPARATOR . self::DIRECTORY;
    }

    public static function fileIn(string $appRootDir): string
    {
        return self::directoryIn($appRootDir) . DIRECTORY_SEPARATOR . self::FILENAME;
    }
}
