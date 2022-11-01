<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Dispatcher;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
