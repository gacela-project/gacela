<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomSuffixTypes\ModuleA;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @method FacaModuleA getFacade()
 */
final class CommandModuleA
{
    use ServiceResolverAwareTrait;

    public function execute(): array
    {
        return $this->getFacade()->doSomething();
    }
}
