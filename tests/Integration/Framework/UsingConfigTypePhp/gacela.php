<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;

return static fn () => new class () extends AbstractConfigGacela {
    public function config(): array
    {
        return [
            'type' => 'php',
            'path' => 'config/*.php',
            'path_local' => 'config/local.php',
        ];
    }
};
