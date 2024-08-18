<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\Greeter;

final class Team
{
    public function getNames(): string
    {
        return 'Chemaclass & Jesus';
    }
}
