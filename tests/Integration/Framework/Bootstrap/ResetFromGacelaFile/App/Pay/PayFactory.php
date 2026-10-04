<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile\App\Pay;

use GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile\Library\Pay\PayFactory as LibraryPayFactory;
use Override;

final class PayFactory extends LibraryPayFactory
{
    #[Override]
    public function who(): string
    {
        return 'app override';
    }
}
