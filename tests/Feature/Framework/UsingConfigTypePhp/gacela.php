<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\GacelaConfigArgs\ConfigResolver;

return static fn () => new class () extends AbstractConfigGacela {
    public function config(ConfigResolver $configResolver): void
    {
        $configResolver->add(PhpConfigReader::class, 'config/*.php', 'config/local.php');
    }
};
