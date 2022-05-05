<?php

declare(strict_types=1);

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Setup\SetupGacela;

return static fn () => (new SetupGacela())
    ->setConfigFn(
        static function (ConfigBuilder $configBuilder): void {
            $configBuilder->add('config/*.php', 'config/local.php');
        }
    );
