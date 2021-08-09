<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;

return static function (array $globalServices = []): AbstractConfigGacela {
    return new class($globalServices) extends AbstractConfigGacela {
        public function config(): array
        {
            return [
                [
                    'type' => 'env',
                    'path' => 'config/.env*',
                ],
                [
                    'type' => 'php',
                    'path' => 'config/*.php',
                ],
                [
                    'type' => 'custom',
                    'path' => 'config/*.custom',
                ],
            ];
        }
    };
};
