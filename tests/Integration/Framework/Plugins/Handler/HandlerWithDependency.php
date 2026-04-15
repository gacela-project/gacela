<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Plugins\Handler;

final class HandlerWithDependency
{
    public function __construct(
        public readonly InjectedDependency $dependency,
    ) {
    }
}
