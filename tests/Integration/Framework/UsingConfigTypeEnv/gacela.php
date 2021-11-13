<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;

return static fn () => new class () extends AbstractConfigGacela {
    public function config(): array
    {
        return [
            'type' => 'env',
            'path' => 'config/.env*',
            'path_local' => 'config/.env.local.dist',
        ];
    }
};
