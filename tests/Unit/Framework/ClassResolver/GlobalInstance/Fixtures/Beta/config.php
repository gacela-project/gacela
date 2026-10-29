<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Gacela;

Gacela::addGlobal(new class() extends AbstractConfig {
    public function get(string $key, mixed $default = null): mixed
    {
        return 'Beta-global';
    }
}, __FILE__);

return new class() {};
