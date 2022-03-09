<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\GacelaConfigArgs\ConfigBuilder;

return static fn () => new class () extends AbstractConfigGacela {
    public function config(ConfigBuilder $configBuilder): void
    {
        $configBuilder->add(PhpConfigReader::class, 'config/*.php', 'config/local.php');
    }
};
