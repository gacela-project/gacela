<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile\App\Pay;

use GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile\Vendor\Pay\PayFactory as VendorPayFactory;
use Override;

final class PayFactory extends VendorPayFactory
{
    #[Override]
    public function who(): string
    {
        return 'app override';
    }
}
