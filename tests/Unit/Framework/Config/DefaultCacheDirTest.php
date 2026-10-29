<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class DefaultCacheDirTest extends TestCase
{
    public function test_the_default_cache_is_private_and_under_the_application_root(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-private-' . uniqid('', true);
        mkdir($root);
        try {
            Gacela::bootstrap($root, static fn (GacelaConfig $config): \Gacela\Framework\Bootstrap\GacelaConfig => $config->enableFileCache());
            $directory = $root . DIRECTORY_SEPARATOR . '.gacela' . DIRECTORY_SEPARATOR . 'cache';
            self::assertSame($directory, Config::getInstance()->getCacheDir());
            self::assertDirectoryExists($directory);
            if (DIRECTORY_SEPARATOR === '/') {
                self::assertSame(0700, fileperms($directory) & 0777);
            }
        } finally {
            Gacela::resetCache();
            DirectoryUtil::removeDir($root);
        }
    }
}
