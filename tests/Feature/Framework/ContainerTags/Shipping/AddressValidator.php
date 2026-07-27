<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Shipping;

use GacelaTest\Feature\Framework\ContainerTags\Validation\ValidatorInterface;

final class AddressValidator implements ValidatorInterface
{
    public function name(): string
    {
        return 'address';
    }
}
