<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerDefinitions\Greeting;

use GacelaTest\Feature\Framework\ContainerDefinitions\Notifying\NotifierInterface;

final class Greeter
{
    public function __construct(
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function notifierName(): string
    {
        return $this->notifier->name();
    }
}
