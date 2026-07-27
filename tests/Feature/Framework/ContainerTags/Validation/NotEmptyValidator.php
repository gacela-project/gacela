<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Validation;

final class NotEmptyValidator implements ValidatorInterface
{
    public function name(): string
    {
        return 'not-empty';
    }
}
