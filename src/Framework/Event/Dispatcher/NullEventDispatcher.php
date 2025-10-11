<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

use Override;

final class NullEventDispatcher implements EventDispatcherInterface
{
    #[Override]
    public function dispatch(object $event): void
    {
    }
}
