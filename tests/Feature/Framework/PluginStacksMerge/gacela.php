<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PluginStacksMerge;

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\Discount;
use GacelaTest\Feature\Framework\PluginStacks\Checkout\FiveHundredOff;

return static function (GacelaConfig $config): void {
    // A second config source contributing to the same extension point the
    // bootstrap closure declares.
    $config->addPluginStack(Discount::class, [FiveHundredOff::class]);
};
