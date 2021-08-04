<?php

declare(strict_types=1);

return [
    'config' => [
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
    ],
];
