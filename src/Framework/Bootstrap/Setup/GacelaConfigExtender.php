<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;

use function is_callable;

final class GacelaConfigExtender
{
    public function extend(GacelaConfig $gacelaConfig): void
    {
        $configsToExtend = $gacelaConfig->toTransfer()->gacelaConfigsToExtend ?? [];

        if ($configsToExtend === []) {
            return;
        }

        $container = new Container();

        foreach ($configsToExtend as $className) {
            /** @var callable|null $configToExtend */
            $configToExtend = $container->get($className);
            if (is_callable($configToExtend)) {
                $configToExtend($gacelaConfig);
            }
        }
    }
}
