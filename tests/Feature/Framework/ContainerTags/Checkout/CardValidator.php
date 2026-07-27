<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Checkout;

use GacelaTest\Feature\Framework\ContainerTags\Validation\ValidatorInterface;

final class CardValidator implements ValidatorInterface
{
    public function name(): string
    {
        return 'card';
    }
}
