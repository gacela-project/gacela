<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleB;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @method FacadeModuleB getFacade()
 */
final class CommandModuleB
{
    use ServiceResolverAwareTrait;

    public function execute(): array
    {
        return $this->getFacade()->doSomething();
    }
}
