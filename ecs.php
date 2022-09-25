<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Basic\BracesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);

    $ecsConfig->sets([SetList::PSR_12]);

    $ecsConfig->skip([
        BracesFixer::class,
    ]);
};
