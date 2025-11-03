<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleC;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @method Facade getFacade()
 */
final class CommandModuleC
{
    use ServiceResolverAwareTrait;

    public function execute(): array
    {
        return $this->getFacade()->doSomething();
    }
}
