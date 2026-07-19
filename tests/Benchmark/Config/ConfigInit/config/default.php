<?php

declare(strict_types=1);

return [
    'app-name' => 'config-init-bench',
    'debug' => false,
    'retries' => 3,
    'timeout-seconds' => 1.5,
    'feature-flags' => [
        'flag-a' => true,
        'flag-b' => false,
        'flag-c' => true,
    ],
    'endpoints' => [
        'primary' => 'https://example.test/api',
        'fallback' => 'https://fallback.example.test/api',
    ],
];
