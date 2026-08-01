<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerDefinitions\Notifying;

final class EmailNotifier implements NotifierInterface
{
    public function name(): string
    {
        return 'email';
    }
}
