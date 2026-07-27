<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Validation;

final class EmailValidator implements ValidatorInterface
{
    public function name(): string
    {
        return 'email';
    }
}
