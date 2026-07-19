<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Testing\GacelaTestCase;

use function file_put_contents;
use function mkdir;

final class PathFinderResetTest extends GacelaTestCase
{
    public function test_config_files_added_after_a_reset_are_visible_to_the_next_bootstrap(): void
    {
        $appRootDir = $this->containerTempDir();
        mkdir($appRootDir . '/config');
        file_put_contents($appRootDir . '/config/first.php', '<?php return ["first-key" => 1];');

        $this->bootstrapWithConfigDir($appRootDir);
        self::assertSame(1, Config::getInstance()->getInt('first-key'));
        self::assertSame('fallback', Config::getInstance()->getString('second-key', 'fallback'));

        // A file added on disk after the first bootstrap must be visible to a
        // re-bootstrap: resetContainer() (Gacela::resetCache()) has to drop the
        // glob results too, not only the resolver/config singletons.
        file_put_contents($appRootDir . '/config/second.php', '<?php return ["second-key" => 2];');
        $this->bootstrapWithConfigDir($appRootDir);

        self::assertSame(1, Config::getInstance()->getInt('first-key'));
        self::assertSame(2, Config::getInstance()->getInt('second-key'));
    }

    private function bootstrapWithConfigDir(string $appRootDir): void
    {
        $this->bootstrapGacela($appRootDir, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->addAppConfig('config/*.php');
        });
    }
}
